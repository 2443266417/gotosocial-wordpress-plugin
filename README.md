# GoToSocial WordPress 说说展示插件

这是一个用于在 WordPress 页面通过短代码展示 GoToSocial 动态（说说）的插件，支持图片放大、评论显示、加载更多等功能。

---

## 功能介绍

- 支持设置 GoToSocial 实例地址、用户名和 Access Token（可选）
- 显示用户动态说说及多图缩略图，点击图片可放大查看
- 显示点赞、转发、评论数量
- 支持“加载更多”按钮分页加载动态
- 支持显示评论列表，点击“查看更多评论”跳转到原文页面查看全部评论
- 朋友圈风格美观样式，适配移动端和 PC

---

## 安装方法

1. 下载本插件源码，上传到 WordPress 插件目录 `/wp-content/plugins/`
2. 在 WordPress 后台插件管理中启用插件
3. 进入【设置】-【GoToSocial 设置】填写：
   - GoToSocial 用户名（必填）
   - 实例地址（例如 https://duanbo.cc）
   - 每页加载数量（默认10）
   - Access Token（必填）
4. 在页面或文章编辑器中添加短代码：[gotosocial_say_ajax]
