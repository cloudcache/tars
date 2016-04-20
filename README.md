``[官方项目](https://github.com/tencent-tars/tars)，已经暂时暂停，有兴趣的可以关注``

# TARS 包发布系统

TARS 包发布系统，是一套提供高效的软件包的管理、部署和运维的平台。标准化的软件包结构，可视化打包，完善的权限、版本控制管理，并提供可靠的进程监控与自恢复能力。

## 开始

1. 部署并配置后台服务和接口 `api` 和 `console` 站点。
2. 修改配置文件 `./console/tars.ini` 。配置基本信息、后台服务API地址等。
3. 调整 `./console/www/assets/js/app.js` 中的一些前台配置项。

[Web 控制台说明](https://github.com/tencent-tars/tars/tree/master/console)

[后台服务和接口说明](https://github.com/tencent-tars/tars/tree/master/api)

## 目录结构

```bash
tars/
│
├── api/                       # 后台服务和接口
│
└── console/                   # Web 控制台

```

## 开发

主要依赖：

- [Angular.js](https://angularjs.org/)
- [Bootstrap](http://getbootstrap.com/)
- [AngularUI Router](https://github.com/angular-ui/ui-router/wiki)
- [Flight](http://flightphp.com/) - PHP 微型框架，用于构建 RESTful 的 Web 应用

```bash
cd console
npm install -g gulp  # NPM 全局安装构建工具 gulp
npm install          # NPM 本地安装 gulp, gulp-sass
gulp                 # 启动 gulp 任务，自动监听 scss 文件变更，生成 css
```

## 版权许可

[BSD](https://github.com/tencent-tars/tars/blob/master/LICENSE)

Copyright (c) 2015, TENCENT, INC.
