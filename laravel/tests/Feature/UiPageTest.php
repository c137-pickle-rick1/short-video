<?php

namespace Tests\Feature;

use Tests\TestCase;

final class UiPageTest extends TestCase
{
    public function test_ui_page_renders_component_showcase(): void
    {
        $response = $this->get('/ui');
        $content = $response->getContent();

        $response->assertOk();
        $response->assertSee('UI Library', false);
        $response->assertSee('搜索视频、作者或关键词', false);
        $response->assertSee('页面框架组件', false);
        $response->assertSee('基础展示组件', false);
        $response->assertSee('Lagos Studio', false);
        $response->assertSee('登录页组件', false);
        $response->assertSee('Buttons', false);
        $response->assertSee('Input Field', false);
        $response->assertSee('Menu', false);
        $response->assertSee('Feed Card / Pending', false);
        $response->assertSee('Login Card / Loading', false);
        $response->assertDontSee('Code', false);
        $response->assertDontSee('data-ui-copy', false);
        $response->assertDontSee('Section Filter', false);
        $response->assertDontSee('State Filter', false);
        $response->assertDontSee('组件展示页', false);
        $response->assertDontSee('路径：<code>/ui</code>', false);
        $response->assertDontSee('Contents', false);
        $response->assertDontSee('基础组件与业务组件目录。', false);
        $response->assertDontSee('On this page', false);
        $response->assertDontSee('当前组件目录按基础组件和业务组件组织，正文只展示组件本体和必要预览，方便直接查看样式与状态。', false);
        $response->assertDontSee('顶部栏和导航相关组件预览。', false);
        $response->assertDontSee('按钮、输入、菜单与身份标识等基础控件。', false);
        $response->assertSee('<body class="min-h-screen overflow-x-hidden bg-white text-stone-950 antialiased">', false);

        $this->assertNotFalse($content);
        $this->assertTrue(strpos($content, 'Buttons') < strpos($content, 'Author Identity / With Avatar'));
        $this->assertTrue(strpos($content, 'Buttons') < strpos($content, 'Input Field'));
        $this->assertTrue(strpos($content, 'Input Field') < strpos($content, 'Menu'));
    }
}
