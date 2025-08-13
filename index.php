<?php
/**
 * API使用向导
 * 提供API使用说明
 */

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>API接口说明</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; margin: 0; padding: 0; background: #f5f5f5; }
        .navbar { background: #3498db; color: #fff; padding: 16px 0; box-shadow: 0 2px 8px #0001; position: sticky; top: 0; z-index: 10; }
        .navbar .nav { max-width: 1200px; margin: 0 auto; display: flex; gap: 32px; align-items: center; }
        .navbar a { color: #fff; text-decoration: none; font-weight: bold; font-size: 16px; transition: color .2s; }
        .navbar a:hover { color: #f1c40f; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin-top: 32px; }
        h1 { color: #2c3e50; margin-bottom: 10px; }
        h2 { color: #34495e; border-bottom: 2px solid #3498db; padding-bottom: 10px; margin-top: 40px; }
        .highlight { background: #f39c12; color: white; padding: 2px 6px; border-radius: 3px; }
        .api-table { width: 100%; border-collapse: collapse; margin: 20px 0; font-size: 15px; }
        .api-table th, .api-table td { border: 1px solid #ddd; padding: 12px; text-align: left; }
        .api-table th { background: #3498db; color: white; }
        .api-table tr:nth-child(even) { background: #f2f2f2; }
        .code { background: #222; color: #f8f8f2; padding: 15px; border-radius: 5px; overflow-x: auto; margin: 10px 0; font-family: 'JetBrains Mono', 'Courier New', monospace; font-size: 15px; }
        .success { background: linear-gradient(90deg,#27ae60 80%,#2ecc71 100%); color: white; padding: 18px; border-radius: 6px; margin: 18px 0; font-size: 16px; border-left: 6px solid #229954; }
        .warning { background: linear-gradient(90deg,#f39c12 80%,#f7ca18 100%); color: white; padding: 18px; border-radius: 6px; margin: 18px 0; font-size: 16px; border-left: 6px solid #e67e22; }
        .info { background: linear-gradient(90deg,#3498db 80%,#5dade2 100%); color: white; padding: 18px; border-radius: 6px; margin: 18px 0; font-size: 16px; border-left: 6px solid #2471a3; }
        .algorithm-info { background: #e8f4fd; padding: 18px; border-radius: 6px; border-left: 6px solid #3498db; margin: 18px 0; font-size: 15px; }
        .feature-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin: 20px 0; }
        .feature-card { background: #2980b9; color: #fff; padding: 20px; border-radius: 8px; border-left: 6px solid #f1c40f; }
        .status-badge { display: inline-block; padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: bold; }
        .status-complete { background: #27ae60; color: white; }
        .status-optimized { background: #f39c12; color: white; }
        hr { border: none; border-top: 1.5px solid #eee; margin: 40px 0 30px 0; }
        @media (max-width: 700px) {
            .container { padding: 10px; }
            .navbar .nav { flex-direction: column; gap: 10px; }
            .feature-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="navbar">
        <div class="nav">
            <a href="#api-guide">API指南</a>
            <a href="#api-table">API对照表</a>
            <a href="#content-api">Content API</a>
        </div>
    </div>
    <div class="container">
        <h2 id="api-guide">🚀 API使用指南</h2>
        <h3>1. 统一入口格式</h3>
        <div class="code">
# 统一API入口
/api/index.php?api=[api_type]&[参数]

        </div>
        <hr>
        <h2 id="api-table">2. 完整API对照表</h2>
        <table class="api-table">
            <thead>
                <tr>
                    <th>API类型</th>
                    <th>主要功能</th>
                    <th>示例URL</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>content</td>
                    <td>章节内容获取（默认full接口，单章）</td>
                    <td>/api/index.php?api=content&item_ids=7276663560427471412</td>
                </tr>
                <tr>
                    <td>content (多章batch)</td>
                    <td>多章节内容（需api_type=batch）</td>
                    <td>/api/index.php?api=content&item_ids=7276663560427471412,7341402209906606616&api_type=batch</td>
                </tr>
                <tr>
                    <td>content (听书)</td>
                    <td>音频播放链接</td>
                    <td>/api/index.php?api=content&ts=听书&item_ids=123</td>
                </tr>
                <tr>
                    <td>content (详情/评论)</td>
                    <td>书籍详细/评论</td>
                    <td>/api/index.php?api=content&book_id=7237397843521047567<br>/api/index.php?api=content&book_id=123&comment=评论</td>
                </tr>
                <tr>
                    <td>chapter</td>
                    <td>章节内容</td>
                    <td>/api/index.php?api=chapter&item_id=123</td>
                </tr>
                <tr>
                    <td>book</td>
                    <td>书籍目录信息</td>
                    <td>/api/index.php?api=book&bookId=123</td>
                </tr>
                <tr>
                    <td>search</td>
                    <td>内容搜索</td>
                    <td>/api/index.php?api=search&key=关键词</td>
                </tr>
                <tr>
                    <td>video</td>
                    <td>短剧视频</td>
                    <td>/api/index.php?api=video&ts=短剧&item_id=123</td>
                </tr>
                <tr>
                    <td>directory</td>
                    <td>目录结构</td>
                    <td>/api/index.php?api=directory&fq_id=123</td>
                </tr>
                <tr>
                    <td>item_info</td>
                    <td>项目详情</td>
                    <td>/api/index.php?api=item_info&item_ids=123</td>
                </tr>
                <tr>
                    <td>full</td>
                    <td>多章节内容</td>
                    <td>/api/index.php?api=full&book_id=123&item_ids=id1,id2</td>
                </tr>
                <tr style="background: #fff3cd;">
                    <td>ios_content</td>
                    <td>iOS专用内容</td>
                    <td>/api/index.php?api=ios_content&item_id=123</td>
                </tr>
                <tr style="background: #fff3cd;">
                    <td>ios_register</td>
                    <td>iOS设备注册</td>
                    <td>/api/index.php?api=ios_register&action=register</td>
                </tr>
                <tr style="background: #d1ecf1;">
                    <td>device_register</td>
                    <td>设备注册</td>
                    <td>/api/index.php?api=device_register&action=register</td>
                </tr>
                <tr style="background: #d1ecf1;">
                    <td>device_register</td>
                    <td>查看当前设备状态</td>
                    <td>/api/index.php?api=device_register&action=status</td>
                </tr>
                <tr style="background: #ffe4e1;">
                    <td>manga</td>
                    <td>漫画图片解密（返回图片URL数组或直接显示图片）</td>
                    <td>/api/index.php?api=manga&item_ids=123<br>/api/index.php?api=manga&item_ids=123&show_html=1</td>
                </tr>
                <tr style="background: #ffe4e1;">
                    <td>raw_full</td>
                    <td>漫画图片解析（不调用本地解密）</td>
                    <td>/api/index.php?api=raw_full&item_id=7012248593146839588</td>
                </tr>
            </tbody>
        </table>
        <hr>
        <h2 id="content-api">3. Content API 详细功能说明</h2>
        <div class="algorithm-info">
            <h4>🔹 漫画接口</h4>
            <div class="code">
# 返回图片URL数组
/api/index.php?api=manga&item_ids=漫画章节ID

# 直接在网页显示图片
/api/index.php?api=manga&item_ids=漫画章节ID&show_html=1
            </div>
            <ul>
                <li>不加show_html参数时，返回JSON格式的图片绝对URL数组，适合前端自定义渲染。</li>
                <li>加show_html=1参数时，直接在网页上显示解密后的漫画图片，适合浏览器直接访问和复制。</li>
            </ul>
            <h4>🔹 单章节内容（默认full接口）</h4>
            <div class="code">
# 单章节内容（默认full接口）
/api/index.php?api=content&item_ids=7276663560427471412
            </div>
            <h4>🔹 多章节内容（batch接口，需api_type=batch）</h4>
            <div class="code">
# 多章节内容（batch接口）
/api/index.php?api=content&item_ids=7276663560427471412,7341402209906606616&api_type=batch
            </div>
            <h4>🔹 自定义URL模式</h4>
            <div class="code">
/api/index.php?api=content&item_ids=123&custom_url=https://example.com/api?device_id={$zwkey2}&item_id={$item_id}
            </div>
            <h4>🔹 听书功能</h4>
            <div class="code">
/api/index.php?api=content&ts=听书&item_ids=7276663560427471412
            </div>
            <h4>🔹 书籍详情</h4>
            <div class="code">
/api/index.php?api=content&book_id=7237397843521047567
            </div>
            <h4>🔹 书籍评论</h4>
            <div class="code">
/api/index.php?api=content&book_id=7237397843521047567&comment=评论&count=10&offset=0
            </div>
        </div>
    </div>
</body>
</html>
