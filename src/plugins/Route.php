<?php
declare(strict_types=1);

namespace think\plugins;

use think\helper\Str;
use think\facade\Event;
use think\facade\Config;
use think\exception\HttpException;

class Route
{
    /**
     * 插件路由请求
     * @return mixed
     */
    public static function execute()
    {
        $app = app();
        $request = $app->request;

        $plugin = $request->route('plugin');
        $controller = $request->route('controller');
        $action = $request->route('action');

        Event::trigger('plugins_begin', $request);

        if (empty($plugin) || empty($controller) || empty($action)) {
            throw new HttpException(500, lang('plugin can not be empty'));
        }

        $request->plugin = $plugin;
        // 设置当前请求的控制器、操作
        $request->setController($controller)->setAction($action);

        // 获取插件基础信息
        $info = get_plugins_info($plugin);
        if (!$info) {
            throw new HttpException(404, lang('plugin %s not found', [$plugin]));
        }
        if (!$info['status']) {
            throw new HttpException(500, lang('plugin %s is disabled', [$plugin]));
        }

        // 监听plugin_module_init
        Event::trigger('plugin_module_init', $request);
        $class = get_plugins_class($plugin, 'controller', $controller);
        if (!$class) {
            throw new HttpException(404, lang('plugin controller %s not found', [Str::studly($controller)]));
        }

        // 重写视图基础路径
        $config = Config::get('view');
        $config['view_path'] = $app->plugins->getpluginsPath() . $plugin . DIRECTORY_SEPARATOR . 'view' . DIRECTORY_SEPARATOR;
        Config::set($config, 'view');

        // 生成控制器对象
        $instance = new $class($app);
        $vars = [];
        if (is_callable([$instance, $action])) {
            // 执行操作方法
            $call = [$instance, $action];
        } elseif (is_callable([$instance, '_empty'])) {
            // 空操作
            $call = [$instance, '_empty'];
            $vars = [$action];
        } else {
            // 操作不存在
            throw new HttpException(404, lang('plugin action %s not found', [get_class($instance).'->'.$action.'()']));
        }
        Event::trigger('plugins_action_begin', $call);

        return call_user_func_array($call, $vars);
    }
}
