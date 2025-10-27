<?php

namespace App\Http\Controllers\Api\Help;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class EmbedController extends Controller
{
    /**
     * Get widget script for embedding help center.
     */
    public function widgetScript(Request $request): Response
    {
        $tenantId = $request->query('tenant');
        
        if (!$tenantId || !is_numeric($tenantId)) {
            abort(400, 'Valid tenant ID is required');
        }

        $baseUrl = config('app.url');
        $frontendUrl = config('app.frontend_url', 'http://localhost:5173');
        
        $script = "
(function() {
    var RC_HELP_CONFIG = {
        tenant: {$tenantId},
        baseUrl: '{$baseUrl}',
        frontendUrl: '{$frontendUrl}'
    };
    
    // Create widget container
    var widget = document.createElement('div');
    widget.id = 'rc-help-widget';
    widget.style.cssText = 'position:fixed;bottom:20px;right:20px;z-index:9999;';
    
    // Create help button
    var button = document.createElement('button');
    button.innerHTML = '?';
    button.style.cssText = 'width:50px;height:50px;border-radius:50%;background:#007bff;color:white;border:none;font-size:20px;cursor:pointer;box-shadow:0 2px 10px rgba(0,0,0,0.2);';
    button.onclick = function() {
        var iframe = document.getElementById('rc-help-iframe');
        if (iframe) {
            iframe.style.display = iframe.style.display === 'none' ? 'block' : 'none';
        } else {
            createHelpCenter();
        }
    };
    
    // Create help center iframe
    function createHelpCenter() {
        var iframe = document.createElement('iframe');
        iframe.id = 'rc-help-iframe';
        iframe.src = '{$baseUrl}/api/help/embed/help?tenant={$tenantId}';
        iframe.style.cssText = 'width:400px;height:600px;border:none;border-radius:8px;box-shadow:0 4px 20px rgba(0,0,0,0.3);position:absolute;bottom:60px;right:0px;display:none;';
        widget.appendChild(iframe);
    }
    
    widget.appendChild(button);
    document.body.appendChild(widget);
})();
";

        return response($script, 200, [
            'Content-Type' => 'application/javascript',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    /**
     * Get help center HTML page for embedding.
     */
    public function publicHelpIndex(Request $request): Response
    {
        $tenantId = $request->query('tenant');
        
        if (!$tenantId || !is_numeric($tenantId)) {
            abort(400, 'Valid tenant ID is required');
        }

        $baseUrl = config('app.url');
        
        $html = "
<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Help Center</title>
    <style>
        body { 
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; 
            margin: 0; 
            padding: 20px; 
            background: #f8f9fa; 
        }
        .container { 
            max-width: 800px; 
            margin: 0 auto; 
            background: white; 
            border-radius: 8px; 
            box-shadow: 0 2px 10px rgba(0,0,0,0.1); 
            overflow: hidden; 
        }
        .header { 
            background: #007bff; 
            color: white; 
            padding: 20px; 
            text-align: center; 
        }
        .content { 
            padding: 20px; 
        }
        .search-box { 
            width: 100%; 
            padding: 12px; 
            border: 1px solid #ddd; 
            border-radius: 4px; 
            font-size: 16px; 
            margin-bottom: 20px; 
        }
        .categories { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); 
            gap: 15px; 
            margin-bottom: 20px; 
        }
        .category { 
            padding: 15px; 
            border: 1px solid #e9ecef; 
            border-radius: 4px; 
            text-decoration: none; 
            color: #333; 
            transition: all 0.2s; 
        }
        .category:hover { 
            border-color: #007bff; 
            background: #f8f9ff; 
        }
        .articles { 
            margin-top: 20px; 
        }
        .article { 
            padding: 15px; 
            border-bottom: 1px solid #e9ecef; 
            text-decoration: none; 
            color: #333; 
            display: block; 
        }
        .article:hover { 
            background: #f8f9fa; 
        }
        .loading { 
            text-align: center; 
            padding: 40px; 
            color: #666; 
        }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>
            <h1>Help Center</h1>
            <p>Find answers to your questions</p>
        </div>
        <div class='content'>
            <input type='text' class='search-box' placeholder='Search articles...' id='searchInput'>
            <div id='categories' class='categories'>
                <div class='loading'>Loading categories...</div>
            </div>
            <div id='articles' class='articles'>
                <div class='loading'>Loading articles...</div>
            </div>
        </div>
    </div>

    <script>
        const tenantId = {$tenantId};
        const baseUrl = '{$baseUrl}';
        
        // Load categories
        fetch(baseUrl + '/api/help/categories?tenant=' + tenantId)
            .then(response => response.json())
            .then(data => {
                const categoriesDiv = document.getElementById('categories');
                if (data.data && data.data.length > 0) {
                    categoriesDiv.innerHTML = data.data.map(category => 
                        '<a href=\"#\" class=\"category\" onclick=\"loadCategoryArticles(' + category.id + ')\">' +
                        '<h3>' + category.name + '</h3>' +
                        '<p>' + (category.description || '') + '</p>' +
                        '</a>'
                    ).join('');
                } else {
                    categoriesDiv.innerHTML = '<p>No categories found.</p>';
                }
            })
            .catch(error => {
                document.getElementById('categories').innerHTML = '<p>Error loading categories.</p>';
            });
        
        // Load articles
        function loadArticles(query = '') {
            let url = baseUrl + '/api/help/articles?tenant=' + tenantId;
            if (query) {
                url += '&query=' + encodeURIComponent(query);
            }
            
            fetch(url)
                .then(response => response.json())
                .then(data => {
                    const articlesDiv = document.getElementById('articles');
                    if (data.data && data.data.length > 0) {
                        articlesDiv.innerHTML = data.data.map(article => 
                            '<a href=\"#\" class=\"article\" onclick=\"openArticle(\'' + article.slug + '\')\">' +
                            '<h3>' + article.title + '</h3>' +
                            '<p>' + (article.summary || '') + '</p>' +
                            '</a>'
                        ).join('');
                    } else {
                        articlesDiv.innerHTML = '<p>No articles found.</p>';
                    }
                })
                .catch(error => {
                    document.getElementById('articles').innerHTML = '<p>Error loading articles.</p>';
                });
        }
        
        // Load articles for specific category
        function loadCategoryArticles(categoryId) {
            fetch(baseUrl + '/api/help/articles?tenant=' + tenantId + '&category=' + categoryId)
                .then(response => response.json())
                .then(data => {
                    const articlesDiv = document.getElementById('articles');
                    if (data.data && data.data.length > 0) {
                        articlesDiv.innerHTML = data.data.map(article => 
                            '<a href=\"#\" class=\"article\" onclick=\"openArticle(\'' + article.slug + '\')\">' +
                            '<h3>' + article.title + '</h3>' +
                            '<p>' + (article.summary || '') + '</p>' +
                            '</a>'
                        ).join('');
                    } else {
                        articlesDiv.innerHTML = '<p>No articles found in this category.</p>';
                    }
                });
        }
        
        // Open article in new window
        function openArticle(slug) {
            window.open(baseUrl + '/api/help/articles/' + slug + '?tenant=' + tenantId, '_blank');
        }
        
        // Search functionality
        document.getElementById('searchInput').addEventListener('input', function(e) {
            const query = e.target.value;
            if (query.length > 2) {
                loadArticles(query);
            } else if (query.length === 0) {
                loadArticles();
            }
        });
        
        // Load initial articles
        loadArticles();
    </script>
</body>
</html>
";

        return response($html, 200, [
            'Content-Type' => 'text/html',
            'Cache-Control' => 'public, max-age=300',
        ]);
    }
}
