<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Symfony\Component\DomCrawler\Crawler;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;

class SeoCrawlerService
{
    protected $client;
    protected $config;

    public function __construct()
    {
        $this->config = Config::get('seo');
        $this->client = new Client([
            'timeout' => $this->config['crawler']['timeout'],
            'headers' => [
                'User-Agent' => $this->config['crawler']['user_agent']
            ],
            'allow_redirects' => [
                'max' => $this->config['crawl']['max_redirects'],
                'strict' => false,
                'referer' => true,
                'protocols' => ['http', 'https']
            ]
        ]);
    }

    /**
     * Crawl a website and extract SEO data
     */
    public function crawlWebsite($url)
    {
        try {
            $crawlData = [
                'crawledAt' => now()->toISOString(),
                'pages' => []
            ];

            $visitedUrls = [];
            $urlsToCrawl = [$url];
            $depth = 0;
            $maxDepth = $this->config['crawl']['max_depth'];

            while (!empty($urlsToCrawl) && $depth < $maxDepth) {
                $currentBatch = $urlsToCrawl;
                $urlsToCrawl = [];

                foreach ($currentBatch as $currentUrl) {
                    if (in_array($currentUrl, $visitedUrls)) {
                        continue;
                    }

                    $pageData = $this->crawlPage($currentUrl);
                    if ($pageData) {
                        $crawlData['pages'][] = $pageData;
                        $visitedUrls[] = $currentUrl;

                        // Extract internal links for next depth
                        if ($depth < $maxDepth - 1) {
                            $internalLinks = $this->extractInternalLinks($pageData['content'] ?? '', $url);
                            $urlsToCrawl = array_merge($urlsToCrawl, $internalLinks);
                        }
                    }

                    // Respect crawl delay
                    if ($this->config['crawl']['crawl_delay'] > 0) {
                        sleep($this->config['crawl']['crawl_delay']);
                    }
                }

                $depth++;
            }

            return $crawlData;

        } catch (\Exception $e) {
            Log::error('SEO Crawler Error: ' . $e->getMessage());
            return [
                'crawledAt' => now()->toISOString(),
                'pages' => [],
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Crawl a single page
     */
    protected function crawlPage($url)
    {
        try {
            $startTime = microtime(true);
            
            $response = $this->client->get($url);
            $content = $response->getBody()->getContents();
            $loadTime = microtime(true) - $startTime;

            $crawler = new Crawler($content);

            return [
                'url' => $url,
                'title' => $this->extractTitle($crawler),
                'metaDescription' => $this->extractMetaDescription($crawler),
                'h1' => $this->extractHeadings($crawler, 'h1'),
                'h2' => $this->extractHeadings($crawler, 'h2'),
                'h3' => $this->extractHeadings($crawler, 'h3'),
                'statusCode' => $response->getStatusCode(),
                'loadTime' => round($loadTime, 2),
                'wordCount' => $this->countWords($content),
                'internalLinks' => $this->countInternalLinks($crawler, $url),
                'externalLinks' => $this->countExternalLinks($crawler, $url),
                'images' => $this->countImages($crawler),
                'imagesWithoutAlt' => $this->countImagesWithoutAlt($crawler),
                'content' => $content
            ];

        } catch (RequestException $e) {
            Log::warning('Failed to crawl page: ' . $url . ' - ' . $e->getMessage());
            return [
                'url' => $url,
                'title' => '',
                'metaDescription' => '',
                'h1' => [],
                'h2' => [],
                'h3' => [],
                'statusCode' => $e->getCode() ?: 500,
                'loadTime' => 0,
                'wordCount' => 0,
                'internalLinks' => 0,
                'externalLinks' => 0,
                'images' => 0,
                'imagesWithoutAlt' => 0,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Extract page title
     */
    protected function extractTitle($crawler)
    {
        try {
            $title = $crawler->filter('title')->first()->text();
            return trim($title);
        } catch (\Exception $e) {
            return '';
        }
    }

    /**
     * Extract meta description
     */
    protected function extractMetaDescription($crawler)
    {
        try {
            $metaDescription = $crawler->filter('meta[name="description"]')->first()->attr('content');
            return trim($metaDescription ?: '');
        } catch (\Exception $e) {
            return '';
        }
    }

    /**
     * Extract headings by level
     */
    protected function extractHeadings($crawler, $level)
    {
        try {
            $headings = [];
            $crawler->filter($level)->each(function ($node) use (&$headings) {
                $headings[] = trim($node->text());
            });
            return $headings;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Count words in content
     */
    protected function countWords($content)
    {
        $text = strip_tags($content);
        $words = preg_split('/\s+/', $text);
        return count(array_filter($words, function($word) {
            return strlen(trim($word)) > 0;
        }));
    }

    /**
     * Count internal links
     */
    protected function countInternalLinks($crawler, $baseUrl)
    {
        try {
            $baseDomain = parse_url($baseUrl, PHP_URL_HOST);
            $count = 0;
            
            $crawler->filter('a[href]')->each(function ($node) use ($baseDomain, &$count) {
                $href = $node->attr('href');
                if ($href) {
                    $linkDomain = parse_url($href, PHP_URL_HOST);
                    if (!$linkDomain || $linkDomain === $baseDomain) {
                        $count++;
                    }
                }
            });
            
            return $count;
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Count external links
     */
    protected function countExternalLinks($crawler, $baseUrl)
    {
        try {
            $baseDomain = parse_url($baseUrl, PHP_URL_HOST);
            $count = 0;
            
            $crawler->filter('a[href]')->each(function ($node) use ($baseDomain, &$count) {
                $href = $node->attr('href');
                if ($href) {
                    $linkDomain = parse_url($href, PHP_URL_HOST);
                    if ($linkDomain && $linkDomain !== $baseDomain) {
                        $count++;
                    }
                }
            });
            
            return $count;
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Count images
     */
    protected function countImages($crawler)
    {
        try {
            return $crawler->filter('img')->count();
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Count images without alt text
     */
    protected function countImagesWithoutAlt($crawler)
    {
        try {
            $count = 0;
            $crawler->filter('img')->each(function ($node) use (&$count) {
                $alt = $node->attr('alt');
                if (empty($alt)) {
                    $count++;
                }
            });
            return $count;
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Extract internal links from content
     */
    protected function extractInternalLinks($content, $baseUrl)
    {
        try {
            $crawler = new Crawler($content);
            $baseDomain = parse_url($baseUrl, PHP_URL_HOST);
            $baseUrlParsed = parse_url($baseUrl);
            $basePath = $baseUrlParsed['path'] ?? '/';
            
            $links = [];
            $crawler->filter('a[href]')->each(function ($node) use ($baseDomain, $baseUrl, $basePath, &$links) {
                $href = $node->attr('href');
                if ($href) {
                    // Convert relative URLs to absolute
                    if (strpos($href, 'http') !== 0) {
                        if (strpos($href, '/') === 0) {
                            $href = $baseUrlParsed['scheme'] . '://' . $baseDomain . $href;
                        } else {
                            $href = rtrim($baseUrl, '/') . '/' . ltrim($href, '/');
                        }
                    }
                    
                    $linkDomain = parse_url($href, PHP_URL_HOST);
                    if ($linkDomain === $baseDomain && !in_array($href, $links)) {
                        $links[] = $href;
                    }
                }
            });
            
            return array_slice($links, 0, $this->config['crawler']['max_pages']);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Extract keywords from content
     */
    public function extractKeywords($content, $source = 'content')
    {
        $text = strip_tags($content);
        $words = preg_split('/\s+/', strtolower($text));
        
        $keywords = array_filter($words, function($word) {
            $word = trim($word, '.,!?;:"()[]{}');
            return strlen($word) >= $this->config['keywords']['min_length'] 
                && strlen($word) <= $this->config['keywords']['max_length']
                && !in_array($word, $this->config['keywords']['excluded_words']);
        });

        // Count frequency
        $keywordCounts = array_count_values($keywords);
        
        return collect($keywordCounts)->map(function($frequency, $keyword) use ($source) {
            return [
                'keyword' => $keyword,
                'frequency' => $frequency,
                'source' => $source
            ];
        })->values()->toArray();
    }

    /**
     * Analyze SEO data and generate recommendations
     */
    public function analyzeSeo($crawlData)
    {
        $issues = [];
        $score = 100;

        foreach ($crawlData['pages'] ?? [] as $page) {
            $pageIssues = $this->analyzePage($page);
            $issues = array_merge($issues, $pageIssues);
        }

        // Calculate overall score
        $score = max(0, $score - (count($issues) * 2));

        return [
            'score' => $score,
            'issues' => $issues,
            'total_pages' => count($crawlData['pages'] ?? []),
            'successful_pages' => count(array_filter($crawlData['pages'] ?? [], function($page) {
                return ($page['statusCode'] ?? 0) === 200;
            }))
        ];
    }

    /**
     * Analyze individual page
     */
    protected function analyzePage($page)
    {
        $issues = [];

        // Check title
        if (empty($page['title'])) {
            $issues[] = [
                'type' => 'missing_title',
                'severity' => 'High',
                'page' => $page['url'],
                'description' => 'Add a descriptive title tag'
            ];
        } elseif (strlen($page['title']) > $this->config['analysis']['max_title_length']) {
            $issues[] = [
                'type' => 'title_too_long',
                'severity' => 'Medium',
                'page' => $page['url'],
                'description' => 'Title is too long (max 60 characters)'
            ];
        }

        // Check meta description
        if (empty($page['metaDescription'])) {
            $issues[] = [
                'type' => 'missing_meta_description',
                'severity' => 'Medium',
                'page' => $page['url'],
                'description' => 'Add a compelling meta description'
            ];
        } elseif (strlen($page['metaDescription']) < $this->config['analysis']['min_meta_description_length']) {
            $issues[] = [
                'type' => 'meta_description_too_short',
                'severity' => 'Low',
                'page' => $page['url'],
                'description' => 'Meta description should be at least 120 characters'
            ];
        } elseif (strlen($page['metaDescription']) > $this->config['analysis']['max_meta_description_length']) {
            $issues[] = [
                'type' => 'meta_description_too_long',
                'severity' => 'Low',
                'page' => $page['url'],
                'description' => 'Meta description should be max 160 characters'
            ];
        }

        // Check H1 tags
        if (empty($page['h1'])) {
            $issues[] = [
                'type' => 'missing_h1',
                'severity' => 'High',
                'page' => $page['url'],
                'description' => 'Add a descriptive H1 tag'
            ];
        } elseif (count($page['h1']) > $this->config['analysis']['max_h1_count']) {
            $issues[] = [
                'type' => 'multiple_h1',
                'severity' => 'Medium',
                'page' => $page['url'],
                'description' => 'Use only one H1 tag per page'
            ];
        }

        // Check word count
        if ($page['wordCount'] < $this->config['analysis']['min_word_count']) {
            $issues[] = [
                'type' => 'low_word_count',
                'severity' => 'Medium',
                'page' => $page['url'],
                'description' => 'Add more content (minimum 100 words)'
            ];
        }

        // Check internal links
        if ($page['internalLinks'] < $this->config['analysis']['min_internal_links']) {
            $issues[] = [
                'type' => 'few_internal_links',
                'severity' => 'Low',
                'page' => $page['url'],
                'description' => 'Add more internal links to improve site structure'
            ];
        }

        // Check page speed
        if ($page['loadTime'] > $this->config['analysis']['max_load_time']) {
            $issues[] = [
                'type' => 'slow_loading',
                'severity' => 'High',
                'page' => $page['url'],
                'description' => 'Optimize page speed (currently ' . $page['loadTime'] . 's)'
            ];
        }

        // Check images without alt text
        if ($page['imagesWithoutAlt'] > 0) {
            $issues[] = [
                'type' => 'missing_alt_text',
                'severity' => 'Medium',
                'page' => $page['url'],
                'description' => "Add alt text to {$page['imagesWithoutAlt']} images"
            ];
        }

        return $issues;
    }
}
