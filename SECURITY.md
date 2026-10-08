# 金玉主题 · 安全加固与部署须知

> 适用对象：站点运维 / 审核自查。本文件说明主题已具备的防护，以及启用严格策略时的部署要点。

## 1. 内容安全策略（CSP）

CSP 默认**关闭**，对未配置站点零行为变更。需要纵深防御（即便漏掉 XSS 也被浏览器兜底拦截）时，由站点方自行启用。

### 启用方式（一行）
在子主题 `functions.php` 或 mu-plugin 中返回策略串，`%NONCE%` 会自动替换为当次请求真实 nonce：

```php
add_filter( 'jinyu_csp_policy', function () {
    return "default-src 'self';"
        . "script-src 'self' 'nonce-%NONCE%';"
        . "style-src 'self' 'nonce-%NONCE%';"
        . "img-src 'self' data: https:;font-src 'self' data:;"
        . "connect-src 'self';object-src 'none';base-uri 'self';"
        . "frame-ancestors 'self'";
} );
```

建议仅前台生效，避免影响后台：

```php
add_filter( 'jinyu_csp_policy', function ( $p ) {
    return is_admin() ? $p : "default-src 'self';script-src 'self' 'nonce-%NONCE%';style-src 'self' 'nonce-%NONCE%';img-src 'self' data: https:;font-src 'self' data:;connect-src 'self';object-src 'none';base-uri 'self';frame-ancestors 'self'";
} );
```

### nonce 覆盖范围
主题自身输出的内联 `<style>` / `<script>` **已全部自动附加一次性 nonce**，启用上述策略不会破坏主题渲染，包括：

- 骨架屏移除脚本、`<noscript>` 兜底样式
- 评论「加载更多」样式、动态样式、首屏关键 CSS、字体变量
- Cookie 合规条脚本、自定义 head/foot CSS
- 维护模式页、邮箱校验回调页片段、后台设置页脚本

### 已知限制（信任边界）
- 「扩展与开发 › 自定义代码」面板中**用户自行填写的完整 `<script>` JS 片段**不在自动 nonce 范围内（自定义 CSS 已自动 nonce）。启用严格 CSP 后此类自定义 JS 需用户自行加 `nonce` 属性或放宽 `script-src`（不推荐 `'unsafe-inline'`）。
- wp.org 变体已用 `jinyu_is_wporg()` 完全禁用自定义代码输出。

## 2. AI API Key 加密存储

- 依赖 `jinyu-theme-companion` 插件的 `jinyu_encrypt` / `jinyu_decrypt` 过滤器。
- 启用 companion 插件：`ai_api_key` 落库经 `jinyu_encrypt` 加密、读取透明解密。
- 未装 companion：按既有行为降级为明文存储（wp.org 变体不暴露 AI 配置项）。
- **建议**：启用 AI 功能即安装 companion 插件，避免密钥明文落库。

## 3. 真实客户端 IP（CDN / 反代场景）

- 默认取 `$_SERVER['REMOTE_ADDR']`。
- 站点前置 CDN / 反向代理时，真实访客 IP 需由 companion 插件经 `jinyu_client_ip` 过滤器穿透；否则速率限制按边缘节点 IP 计，可能失效或误伤单 IP。

## 4. 速率限制

主题自带基于 transient 的双层限流（站点总闸 + 单 IP），覆盖登录 / 注册 / 找回密码 / 评论 / 点赞 / 投票 / 搜索，**默认开启且插件缺席仍真实拦截**。

## 5. 其他已落实的防护（自查结论）

- 所有 AJAX 端点 `check_ajax_referer` + 能力校验；设置保存 / 导入 / 导出 / 删除均 `current_user_can('edit_theme_options')`。
- 输入消毒 + 输出转义（`esc_*` / `wp_kses`）覆盖彻底；无 `eval` / `base64_decode` / `exec` / 未净化 `unserialize`；`$wpdb` 直查均 `$wpdb->prepare()`。
- 文件上传走 `mime_content_type()` 真实 MIME + 扩展名白名单，无路径遍历。
- 密钥不下发头的一方绝不自行生成 nonce（默认空串 + 过滤器交给下发方），符合解耦约定。

## 6. 安全事件反馈

发现漏洞请在仓库提交 issue，或邮件至维护者。请勿在公开渠道披露细节直至修复发布。
