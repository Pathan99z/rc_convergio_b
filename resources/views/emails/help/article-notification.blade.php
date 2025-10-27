<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Article {{ ucfirst($action) }} Notification</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .article-card {
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
            background-color: #fff;
        }
        .article-title {
            font-size: 24px;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 10px;
        }
        .article-summary {
            color: #6c757d;
            font-size: 16px;
            margin-bottom: 15px;
        }
        .article-meta {
            font-size: 14px;
            color: #6c757d;
            border-top: 1px solid #e9ecef;
            padding-top: 15px;
            margin-top: 15px;
        }
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background-color: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin: 10px 0;
        }
        .btn:hover {
            background-color: #0056b3;
        }
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e9ecef;
            font-size: 12px;
            color: #6c757d;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Knowledge Base Article {{ ucfirst($action) }}</h1>
        <p>Hello {{ $recipient->name }},</p>
        <p>An article has been {{ $action }} in the Knowledge Base.</p>
    </div>

    <div class="article-card">
        <div class="article-title">{{ $article->title }}</div>
        
        @if($article->summary)
            <div class="article-summary">{{ $article->summary }}</div>
        @endif

        @if($article->category)
            <p><strong>Category:</strong> {{ $article->category->name }}</p>
        @endif

        <div class="article-meta">
            <p><strong>Status:</strong> {{ ucfirst($article->status) }}</p>
            <p><strong>Published:</strong> {{ $article->published_at?->format('M d, Y \a\t g:i A') ?? 'Not published' }}</p>
            <p><strong>Views:</strong> {{ number_format($article->views) }}</p>
            @if($article->helpful_count > 0 || $article->not_helpful_count > 0)
                <p><strong>Helpfulness:</strong> 
                    {{ $article->helpful_count }} helpful, 
                    {{ $article->not_helpful_count }} not helpful
                    ({{ $article->helpful_count + $article->not_helpful_count > 0 ? round(($article->helpful_count / ($article->helpful_count + $article->not_helpful_count)) * 100) : 0 }}% helpful)
                </p>
            @endif
        </div>

        <a href="{{ $articleUrl }}" class="btn">View Article</a>
    </div>

    <div class="footer">
        <p>This notification was sent because you have access to the Knowledge Base.</p>
        <p>If you no longer wish to receive these notifications, please contact your administrator.</p>
        <p>&copy; {{ date('Y') }} RC Convergio. All rights reserved.</p>
    </div>
</body>
</html>
