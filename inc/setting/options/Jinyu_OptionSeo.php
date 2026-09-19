<?php

namespace Jinyu\Theme\setting\options;
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
class Jinyu_OptionSeo extends Jinyu_BaseOptionItem
{
    public function get_fields(): array
    {
        return [
            'key'    => 'seo',
            'title'  => __('SEO 搜索优化', JINYU),
            'desc'   => __('标题关键词描述、sitemap 与搜索引擎收录设置。', JINYU),
            'icon'   => 'fa-solid fa-search',
            'fields' => [
                ['id'=>'seo_open','title'=>__('启用主题内置 SEO',JINYU),'type'=>'switch','sdt'=>true,'desc'=>__('已安装 Yoast/RankMath 时请关闭',JINYU)],
                ['id'=>'ld_json_disable','title'=>__('关闭 JSON-LD 结构化数据',JINYU),'type'=>'switch','sdt'=>false,'desc'=>__('主题默认输出 Article/Breadcrumb/FAQ 等结构化数据；已用 SEO 插件接管时开启',JINYU)],
                ['id'=>'seo_desc','title'=>__('默认站点描述',JINYU),'type'=>'textarea','sdt'=>__('欢迎访问本站，这里专注于分享 ……（用一句话概括站点主题，将作为搜索引擎结果中的描述摘要）',JINYU)],
                ['id'=>'seo_keywords','title'=>__('默认关键词 (逗号分隔)',JINYU),'type'=>'string','sdt'=>__('WordPress,博客,技术分享,教程',JINYU)],
                ['id'=>'no_category','title'=>__('去除分类链接中的 category 前缀',JINYU),'type'=>'switch','sdt'=>false,'desc'=>__('开启后分类URL变为 /分类名/，首次开启后会自动刷新重写规则（或到「设置→固定链接」保存一次）',JINYU)],
                /* ─── Open Graph / Twitter Card ─── */
                ['id'=>'og_site_name','title'=>__('Open Graph 站点名称',JINYU),'type'=>'string','sdt'=>'','desc'=>__('留空则使用站点标题',JINYU)],
                ['id'=>'twitter_card_enable','title'=>__('输出 Twitter Card',JINYU),'type'=>'switch','sdt'=>false,'desc'=>__('为单篇文章补充 twitter:card / twitter:title / twitter:image 等标签',JINYU)],
                ['id'=>'llms_enable','title'=>__('输出 /llms.txt (GEO)',JINYU),'type'=>'switch','sdt'=>true,'desc'=>__('为 AI 助理（ChatGPT/Claude/Perplexity/元宝 等）提供结构化站点内容地图，利于被 AI 搜索引用；关闭后访问 /llms.txt 返回 404',JINYU)],
                ['id'=>'auto_link_enable','title'=>__('自动内链',JINYU),'type'=>'switch','sdt'=>true,'desc'=>__('正文里出现的其它文章标题自动转为站内链接，打通站内权重、利于收录；单篇最多链 5 处、每词仅首次出现时链、跳过标题/代码块；发文章后自动重建索引',JINYU)],

                /* ─── 实体图谱 / sameAs（E-E-A-T，利于 AI 引用） ─── */
                ['id'=>'entity_sameas','title'=>__('实体关联档案 (sameAs)',JINYU),'type'=>'textarea','sdt'=>'','rows'=>7,'placeholder'=>"https://www.zhihu.com/people/[你的知乎ID]\nhttps://weibo.com/[你的微博UID或域名]\nhttps://github.com/[你的GitHub用户名]\nhttps://juejin.cn/user/[你的掘金ID]\nhttps://blog.csdn.net/[你的CSDN用户名]\nhttps://www.jianshu.com/u/[你的简书ID]\nhttps://space.bilibili.com/[你的B站UID]",'desc'=>__('每行一个 URL，指向本站/作者在其它平台的官方档案（知乎、GitHub、微博、官网等）。将写入 Article 的 JSON-LD sameAs，帮助搜索引擎与 AI 将本站归并为同一真实实体，提升信任度与引用率。仅填你真实拥有且公开的，留空则不出 sameAs',JINYU)],

                /* ─── IndexNow 主动推送（利于被 AI 搜索快速收录） ─── */
                ['id'=>'indexnow_enable','title'=>__('启用 IndexNow 主动推送',JINYU),'type'=>'switch','sdt'=>true,'desc'=>__('文章发布/更新时主动通知 Bing/Yandex 等秒级重新抓取，利于被 AI 搜索（如 Copilot）快速收录；密钥与验证文件由主题自动生成与管理',JINYU)],
                ['id'=>'baidu_auto_submit','title'=>__('百度主动推送',JINYU),'type'=>'switch','sdt'=>false,'desc'=>__('发布/更新文章时主动推送 URL 到百度站长平台（需填写下方接口调用地址）',JINYU)],
                ['id'=>'baidu_submit_token','title'=>__('百度推送接口地址',JINYU),'type'=>'text','sdt'=>'','desc'=>__('格式：http://data.zz.baidu.com/urls?site=你的域名&token=你的token',JINYU)],

                /* ─── 站点地图 / 404 ─── */
                ['id'=>'sitemap_enable','title'=>__('启用站点地图页',JINYU),'type'=>'switch','sdt'=>true,'desc'=>__('开启后可通过 /sitemap 或「页面模板=站点地图」查看站点地图',JINYU)],
                ['id'=>'custom_404','title'=>__('404 自定义内容',JINYU),'type'=>'textarea','sdt'=>"抱歉，您访问的页面不存在或已被移除。\n您可以返回首页，或使用上方搜索查找需要的内容。",'rows'=>4,'desc'=>__('支持 HTML，将显示在 404 标题下方；留空则只显示默认提示',JINYU)],
            ],
        ];
    }
}
