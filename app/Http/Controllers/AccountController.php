<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\AccountType;
use App\Models\Role;
use App\Http\Requests\StoreAccountRequest;
use App\Http\Requests\UpdateAccountRequest;
use App\Http\Resources\AccountResource;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\Request;
use Carbon\Carbon;

class AccountController extends Controller
{
    private $config = [
        'key' => 'id' # Use as prefix account actions and account infos
    ];

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $_with = $this->_with;
        $accounts = Account::with($_with)->paginate(15);
        return AccountResource::collection($accounts);
    }

    /**
     * Display a listing of the resource to manage.
     *
     * @return \Illuminate\Http\Response
     */
    public function manage()
    {
        $search = $this->_search;
        $_with = $this->_with;
        $isManager = auth()->user()->can('manage', 'App\Models\Account');

        if ($isManager) {
            $baseQuery = new Account;
        } else {
            $baseQuery = auth()->user()->accounts();
        };

        $accounts = $baseQuery->where(
            fn ($query) =>  $query
                ->where('username', 'LIKE', "%{$search}%")
                ->orWhere('description', 'LIKE', "%{$search}%")
                ->orWhere('id', $search)
                ->orWhere('cost', $search)
        )
            ->with($_with)
            ->paginate(15);

        return AccountResource::collection($accounts);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoreAccountRequest $request, AccountType $accountType)
    {
        $game = $accountType->game;

        // Get role use to create account
        $roleThatUsing = auth()->user()->roles->find($request->roleKey);
        if (is_null($roleThatUsing)) {
            return response()->json([
                'errors' => [
                    'roleKey' => 'Vai trò không hợp lệ.'
                ]
            ], 422);
        }

        // Validate
        {
            // Validate Account infos
            $validate = Validator::make(
                $request->accountInfos ?? [], # case accountInfo is null
                $this->makeRuleAccountInfos($accountType->accountInfos, $roleThatUsing),
            );
            if ($validate->fails()) {
                return response()->json([
                    'message' => 'Thông tin tài khoản không hợp lệ.',
                    'errors' => ['accountInfos' => $validate->errors()],
                ], 422);
            }

            // Validate Account actions
            $validate = Validator::make(
                $request->accountActions ?? [], # case accountInfo is null
                $this->makeRuleAccountActions($accountType->accountActions, $roleThatUsing),
            );
            if ($validate->fails()) {
                return response()->json([
                    'message' => 'Một số hành động bắt buộc đối với tài khoản còn thiếu.',
                    'errors' => ['accountActions' => $validate->errors()],
                ], 422);
            }

            // Validate Game infos
            $validate = Validator::make(
                $request->gameInfos ?? [], # case accountInfo is null
                $this->makeRuleGameInfos($game->gameInfos, $roleThatUsing),
            );
            if ($validate->fails()) {
                return response()->json([
                    'message' => 'Một số thông tin game không hợp lệ.',
                    'errors' => ['gameInfos' => $validate->errors()],
                ], 422);
            }
        }

        // Make data to save
        {
            // Initialize data
            $account = new Account;
            foreach ([
                'username', 'password', 'cost', 'description'
            ] as $key) {
                if ($request->filled($key)) {
                    $snackKey = Str::snake($key);
                    $account->$snackKey = $request->$key;
                }
            }

            // Process other account info
            $account->account_type_id = $accountType->getKey();
            $account->last_role_key_editor_used = $roleThatUsing->getKey();

            // Process advance account info
            $account->status_code = $this->getStatusCode($accountType, $roleThatUsing);
        }

        try {
            DB::beginTransaction();
            $imagePathsNeedDeleteWhenFail = [];

            // handle representative
            if ($request->hasFile('representativeImage')) {
                $account->representative_image_path
                    = $request->representativeImage->store('public/account-images');
                $imagePathsNeedDeleteWhenFail[] = $account->representative_image_path;
            }

            // Save account in database
            $account->save();

            // Handle relationship
            {
                // Account info
                $syncInfos = [];
                foreach ($request->accountInfos ?? [] as $key => $value) {
                    $id = (int)trim($key, $this->config['key']);
                    if ($accountType->accountInfos->contains($id)) {
                        $syncInfos[$id] =  ['value' => $value];
                    }
                }
                $account->accountInfos()->sync($syncInfos);

                // Account action
                $syncActions = [];
                foreach ($request->accountActions ?? [] as $key => $value) {
                    $id = (int)trim($key, $this->config['key']);
                    if ($accountType->accountActions->contains($id)) {
                        $syncActions[$id] = ['is_done' => $value];
                    }
                }
                $account->accountActions()->sync($syncActions);

                // game info
                $syncGameInfos = [];
                foreach ($request->gameInfos ?? [] as $key => $value) {
                    $id = (int)trim($key, $this->config['key']);
                    if ($game->gameInfos->contains($id)) {
                        $syncGameInfos[$id] = ['value' => $value];
                    }
                }
                $account->gameInfos()->sync($syncGameInfos);
            }

            // handle sub account images
            if ($request->hasFile('images')) {
                foreach ($request->images as $image) {
                    $imagePath = $image->store('public/account-images');
                    $imagePathsNeedDeleteWhenFail[] = $imagePath;
                    $account->images()->create(['path' => $imagePath]);
                }
            }

            DB::commit();
        } catch (\Throwable $th) {
            DB::rollback();
            // Handle delete images
            foreach ($imagePathsNeedDeleteWhenFail as $imagePath) {
                Storage::delete($imagePath);
            }
            throw $th;
        }

        return AccountResource::withLoadRelationships($account->refresh());
    }

    /**
     * Approve account to publish account to user buyable.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Account  $account
     * @return \Illuminate\Http\Response
     */
    public function approve(Request $request, Account $account)
    {
        try {

            switch ($account->status_code) {
                case 0:
                    $account->status_code = 480;
                    break;

                default:
                    return response()->json([
                        'message' => 'Account don\'t allow approve.',
                    ], 503);
                    break;
            }

            // When success
            $account->approved_at = Carbon::now();
            $account->save();
        } catch (\Throwable $th) {
            throw $th;
        }

        // Done
        return AccountResource::withLoadRelationships($account);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Account  $account
     * @return \Illuminate\Http\Response
     */
    public function show(Account $account)
    {
        return AccountResource::withLoadRelationships($account);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Account  $account
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateAccountRequest $request, Account $account)
    {
        $accountType = $account->accountType;
        $game = $accountType->game;

        // Get role use to update
        $roleThatUsing = auth()->user()->roles->find($request->roleKey ?? $account->last_role_key_editor_used);
        if (is_null($roleThatUsing)) {
            return response()->json([
                'errors' => [
                    'roleKey' => 'Vai trò không hợp lệ.'
                ]
            ], 422);
        }

        // Validate account info and account action
        {
            // Validate Account infos
            if ($request->accountInfos) {
                $validate = Validator::make(
                    $request->accountInfos ?? [], # case accountInfo is null
                    $this->makeRuleAccountInfos($accountType->accountInfos, $roleThatUsing),
                );
                if ($validate->fails()) {
                    return response()->json([
                        'message' => 'Thông tin tài khoản không hợp lệ.',
                        'errors' => ['accountInfos' => $validate->errors()],
                    ], 422);
                }
            }

            // Validate Account actions
            if ($request->accountActions) {
                $validate = Validator::make(
                    $request->accountActions ?? [], # case accountAction is null
                    $this->makeRuleAccountActions($accountType->accountActions, $roleThatUsing),
                );
                if ($validate->fails()) {
                    return response()->json([
                        'message' => 'Một số hành động bắt buộc đối với tài khoản còn thiếu.',
                        'errors' => ['accountActions' => $validate->errors()],
                    ], 422);
                }
            }

            // Validate Game infos
            if ($request->gameInfos) {
                $validate = Validator::make(
                    $request->gameInfos ?? [], # case accountInfo is null
                    $this->makeRuleGameInfos($game->gameInfos, $roleThatUsing),
                );
                if ($validate->fails()) {
                    return response()->json([
                        'message' => 'Một số thông tin game không hợp lệ.',
                        'errors' => ['gameInfos' => $validate->errors()],
                    ], 422);
                }
            }
        }

        // Make data to save
        {
            // Initialize data
            foreach ([
                'username', 'password', 'cost', 'description'
            ] as $key) {
                if ($request->filled($key)) {
                    $snackKey = Str::snake($key);
                    $account->$snackKey = $request->$key;
                }
            }

            // Process other account info
            $account->last_role_key_editor_used = $roleThatUsing->getKey();
        }


        try {
            DB::beginTransaction();
            $imagePathsNeedDeleteWhenFail = [];
            $imagePathsNeedDeleteWhenSuccess = [];

            // handle representative
            if ($request->hasFile('representativeImage')) {
                $imagePathsNeedDeleteWhenSuccess[]
                    = $account->representative_image_path;
                $account->representative_image_path
                    = $request->representativeImage
                    ->store('public/account-images');
                $imagePathsNeedDeleteWhenFail[]
                    = $account->representative_image_path;
            }

            // Save account in database
            $account->save();

            // Handle relationship
            {
                // account infos
                if ($request->accountInfos) {
                    $syncInfos = [];
                    foreach ($request->accountInfos ?? [] as $key => $value) {
                        $id = (int)trim($key, $this->config['key']);
                        if ($accountType->accountInfos->contains($id)) {
                            $syncInfos[$id] =  ['value' => $value];
                        }
                    }
                    $account->accountInfos()->sync($syncInfos);
                }


                // account actions
                if ($request->accountActions) {
                    $syncActions = [];
                    foreach ($request->accountActions ?? [] as $key => $value) {
                        $id = (int)trim($key, $this->config['key']);
                        if ($accountType->accountActions->contains($id)) {
                            $syncActions[$id] = ['is_done' => $value];
                        }
                    }
                    $account->accountActions()->sync($syncActions);
                }

                // game info
                if ($request->gameInfos) {
                    $syncGameInfos = [];
                    foreach ($request->gameInfos ?? [] as $key => $value) {
                        $id = (int)trim($key, $this->config['key']);
                        if ($game->gameInfos->contains($id)) {
                            $syncGameInfos[$id] = ['value' => $value];
                        }
                    }
                    $account->gameInfos()->sync($syncGameInfos);
                }

                // sub account images
                if ($request->hasFile('images')) {
                    foreach ($request->images as $image) {
                        $imagePath = $image->store('public/account-images');
                        $imagePathsNeedDeleteWhenFail[] = $imagePath;
                        $account->images()->create(['path' => $imagePath]);
                    }
                }
            }

            // When success
            foreach ($imagePathsNeedDeleteWhenSuccess as $imagePath) {
                Storage::delete($imagePath);
            }
            DB::commit();
        } catch (\Throwable $th) {
            DB::rollback();
            // Handle delete images
            foreach ($imagePathsNeedDeleteWhenFail as $imagePath) {
                Storage::delete($imagePath);
            }
            throw $th;
        }

        return AccountResource::withLoadRelationships($account);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Account  $account
     * @return \Illuminate\Http\Response
     */
    public function destroy(Account $account)
    {
        return false; // don't allow destroy account

        // DB transaction
        try {
            DB::beginTransaction();
            $imagePathsNeedDeleteWhenSuccess = [];

            // Get image must delete
            $imagePathsNeedDeleteWhenSuccess[] = $account->representative_image_path;
            foreach ($account->images as $image) {
                $imagePathsNeedDeleteWhenSuccess[] = $image->path;
            }

            $account->images()->delete(); // Delete account images
            $account->delete(); // Delete account

            // When success
            foreach ($imagePathsNeedDeleteWhenSuccess as $imagePath) {
                Storage::delete($imagePath);
            }
            DB::commit();
        } catch (\Throwable $th) {
            DB::rollback();
            throw $th;
        }

        return response()->json([
            'message' => 'Xoá tài khoản thành công.',
        ], 200);
    }

    // -------------------------------------------------------
    // -------------------------------------------------------
    // -------------------------------------------------------
    // -------------------------------------------------------

    private function makeRuleAccountInfos($accountInfos, Role $role)
    {
        // Initial data
        $rules = [];
        foreach ($accountInfos as $accountInfo) {
            // Get rule
            $rule = $accountInfo->rule->generateRule($role);

            // Make rule for validate
            if ($rule['nested']) { # if nested
                $rules[$this->config['key'] . $accountInfo->id] = $rule['ruleOfParent'];
                $rules[$this->config['key'] . $accountInfo->id . '.*'] = $rule['ruleOfChild'];
            } else {
                $rules[$this->config['key'] . $accountInfo->id] = $rule['rule'];
            }
        }

        return $rules;
    }

    private function makeRuleAccountActions($accountActions, Role $role)
    {

        // Initial data
        $rules = [];
        foreach ($accountActions as $accountAction) {
            // Make rule
            $rule = $accountAction->generateRule($role);
            $rules[$this->config['key'] . $accountAction->id] = $rule;
        }

        return $rules;
    }

    private function makeRuleGameInfos($gameInfos, Role $role)
    {
        // Initial data
        $rules = [];
        foreach ($gameInfos as $gameInfo) {
            // Get rule
            $rule = $gameInfo->rule->generateRule($role);

            // Make rule for validate
            if ($rule['nested']) { # if nested
                $rules[$this->config['key'] . $gameInfo->getKey()] = $rule['ruleOfParent'];
                $rules[$this->config['key'] . $gameInfo->getKey() . '.*'] = $rule['ruleOfChild'];
            } else {
                $rules[$this->config['key'] . $gameInfo->getKey()] = $rule['rule'];
            }
        }

        return $rules;
    }

    private function getStatusCode(AccountType $accountType, Role $role)
    {
        return $accountType->rolesCanUsedAccountType
            ->find($role->getKey())
            ->pivot->status_code;
    }
}
