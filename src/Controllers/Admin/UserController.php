<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\AuthController;
use App\Controllers\BaseController;
use App\Models\User;
use App\Models\UserMoneyLog;
use App\Services\Auth;
use App\Utils\Cookie;
use App\Utils\Hash;
use App\Utils\Tools;
use Exception;
use Psr\Http\Message\ResponseInterface;
use Slim\Http\Response;
use Slim\Http\ServerRequest;
use function time;

final class UserController extends BaseController
{
    public static array $details = [
        'field' => [
            'op' => '操作',
            'id' => '用户ID',
            'user_name' => '昵称',
            'email' => '邮箱',
            'money' => '余额',
            'ref_by' => '邀请人',
            'transfer_enable' => '流量限制',
            'last_day_t' => '累计用量',
            'class' => '等级',
            'reg_date' => '注册时间',
            'expire_in' => '账户过期',
            'class_expire' => '等级过期',
            'uuid' => 'UUID',
        ],
        'create_dialog' => [
            [
                'id' => 'email',
                'info' => '登录邮箱',
                'type' => 'input',
                'placeholder' => '',
            ],
            [
                'id' => 'password',
                'info' => '登录密码',
                'type' => 'input',
                'placeholder' => '留空则随机生成',
            ],
            [
                'id' => 'ref_by',
                'info' => '邀请人',
                'type' => 'input',
                'placeholder' => '邀请人的用户id，可留空',
            ],
            [
                'id' => 'balance',
                'info' => '账户余额',
                'type' => 'input',
                'placeholder' => '-1为按默认设置，其他为指定值',
            ],
        ],
    ];

    public static array $update_field = [
        'email',
        'user_name',
        'remark',
        'pass',
        'money',
        'is_admin',
        'ga_enable',
        'use_new_shop',
        'is_banned',
        'banned_reason',
        'transfer_enable',
        'invite_num',
        'ref_by',
        'class_expire',
        'expire_in',
        'node_group',
        'class',
        'auto_reset_day',
        'auto_reset_bandwidth',
        'node_speedlimit',
        'node_iplimit',
        'port',
        'uuid',
        'passwd',
        'method',
        'forbidden_ip',
        'forbidden_port',
    ];

    /**
     * @throws Exception
     */
    public function index(ServerRequest $request, Response $response, array $args): Response|ResponseInterface
    {
        return $response->write(
            $this->view()
                ->assign('details', self::$details)
                ->fetch('admin/user/index.tpl')
        );
    }

    public function createNewUser(ServerRequest $request, Response $response, array $args): Response|ResponseInterface
    {
        $email = $request->getParam('email');
        $ref_by = $request->getParam('ref_by');
        $password = $request->getParam('password');
        $balance = $request->getParam('balance');

        try {
            if ($email === '') {
                throw new Exception('请填写邮箱');
            }
            if (! Tools::isEmailLegal($email)) {
                throw new Exception('邮箱格式不正确');
            }
            $exist = User::where('email', $email)->first();
            if ($exist !== null) {
                throw new Exception('此邮箱已注册');
            }
            if ($password === '') {
                $password = Tools::genRandomChar(16);
            }
            AuthController::registerHelper($response, 'user', $email, $password, '', 1, '', 0, $balance, 1);
            $user = User::where('email', $email)->first();
            if ($ref_by !== '') {
                $user->ref_by = (int) $ref_by;
                $user->save();
            }
        } catch (Exception $e) {
            return $response->withJson([
                'ret' => 0,
                'msg' => $e->getMessage(),
            ]);
        }

        return $response->withJson([
            'ret' => 1,
            'msg' => '添加成功，用户邮箱：'.$email.' 密码：'.$password,
        ]);
    }

    /**
     * @throws Exception
     */
    public function edit(ServerRequest $request, Response $response, array $args): Response|ResponseInterface
    {
        $user = User::find($args['id']);

        return $response->write(
            $this->view()
                ->assign('update_field', self::$update_field)
                ->assign('edit_user', $user)
                ->fetch('admin/user/edit.tpl')
        );
    }

    public function update(ServerRequest $request, Response $response, array $args): Response|ResponseInterface
    {
        $id = (int) $args['id'];
        $user = User::find($id);

        if ($request->getParam('pass') !== '' && $request->getParam('pass') !== null) {
            $user->pass = Hash::passwordHash($request->getParam('pass'));
            $user->cleanLink();
        }

        if ($request->getParam('money') !== '' &&
            $request->getParam('money') !== null &&
            (float) $request->getParam('money') !== (float) $user->money
        ) {
            $money = (float) $request->getParam('money');
            $diff = $money - $user->money;
            $remark = ($diff > 0 ? '管理员添加余额' : '管理员扣除余额');
            (new UserMoneyLog())->addMoneyLog($id, (float) $user->money, $money, $diff, $remark);
            $user->money = $money;
        }

        $user->email = $request->getParam('email');
        $user->user_name = $request->getParam('user_name');
        $user->remark = $request->getParam('remark');
        $user->is_admin = $request->getParam('is_admin') === 'true' ? 1 : 0;
        $user->ga_enable = $request->getParam('ga_enable') === 'true' ? 1 : 0;
        $user->use_new_shop = $request->getParam('use_new_shop') === 'true' ? 1 : 0;
        $user->is_banned = $request->getParam('is_banned') === 'true' ? 1 : 0;
        $user->banned_reason = $request->getParam('banned_reason');
        $user->transfer_enable = Tools::toGB($request->getParam('transfer_enable'));
        $user->invite_num = $request->getParam('invite_num');
        $user->ref_by = $request->getParam('ref_by');
        $user->class_expire = $request->getParam('class_expire');
        $user->expire_in = $request->getParam('expire_in');
        $user->node_group = $request->getParam('node_group');
        $user->class = $request->getParam('class');
        $user->auto_reset_day = $request->getParam('auto_reset_day');
        $user->auto_reset_bandwidth = $request->getParam('auto_reset_bandwidth');
        $user->node_speedlimit = $request->getParam('node_speedlimit');
        $user->node_iplimit = $request->getParam('node_iplimit');
        $user->port = $request->getParam('port');
        $user->uuid = $request->getParam('uuid');
        $user->passwd = $request->getParam('passwd');
        $user->method = $request->getParam('method');
        $user->forbidden_ip = str_replace(PHP_EOL, ',', $request->getParam('forbidden_ip'));
        $user->forbidden_port = str_replace(PHP_EOL, ',', $request->getParam('forbidden_port'));

        if (! $user->save()) {
            return $response->withJson([
                'ret' => 0,
                'msg' => '修改失败',
            ]);
        }
        return $response->withJson([
            'ret' => 1,
            'msg' => '修改成功',
        ]);
    }

    public function delete(ServerRequest $request, Response $response, array $args): Response|ResponseInterface
    {
        $id = $args['id'];
        $user = User::find((int) $id);

        if (! $user->killUser()) {
            return $response->withJson([
                'ret' => 0,
                'msg' => '删除失败',
            ]);
        }

        return $response->withJson([
            'ret' => 1,
            'msg' => '删除成功',
        ]);
    }

    public function changetouser(ServerRequest $request, Response $response, array $args): Response|ResponseInterface
    {
        $userid = $request->getParam('userid');
        $adminid = $request->getParam('adminid');
        $user = User::find($userid);
        $admin = User::find($adminid);
        $expire_in = time() + 60 * 60;

        if (! $admin->is_admin || ! $user || ! Auth::getUser()->isLogin) {
            return $response->withJson([
                'ret' => 0,
                'msg' => '非法请求',
            ]);
        }

        Cookie::set([
            'uid' => $user->id,
            'email' => $user->email,
            'key' => Hash::cookieHash($user->pass, $expire_in),
            'ip' => Hash::ipHash($_SERVER['REMOTE_ADDR'], $user->id, $expire_in),
            'expire_in' => $expire_in,
            'old_uid' => Cookie::get('uid'),
            'old_email' => Cookie::get('email'),
            'old_key' => Cookie::get('key'),
            'old_ip' => Cookie::get('ip'),
            'old_expire_in' => Cookie::get('expire_in'),
            'old_local' => $request->getParam('local'),
        ], $expire_in);

        return $response->withJson([
            'ret' => 1,
            'msg' => '切换成功',
        ]);
    }

    public function ajax(ServerRequest $request, Response $response, array $args): Response|ResponseInterface
    {
        $users = User::orderBy('id', 'desc')->get();

        foreach ($users as $user) {
            $user->op = '<button type="button" class="btn btn-red" id="delete-user-' . $user->id . '" 
            onclick="deleteUser(' . $user->id . ')">删除</button>
            <a class="btn btn-blue" href="/admin/user/' . $user->id . '/edit">编辑</a>';
            $user->transfer_enable = round($user->transfer_enable / 1073741824, 2);
            $user->last_day_t = round($user->last_day_t / 1073741824, 2);
        }

        return $response->withJson([
            'users' => $users,
        ]);
    }
}
