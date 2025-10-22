<?php

namespace App\Services\Cms;

use App\Models\Cms\Page;
use App\Models\Cms\SeoLog;
use Illuminate\Support\Facades\Log;

class SeoAnalyzer
{
    /**
     * Analyze a page for SEO issues and recommendations.
     */
    public function analyzePage(Page $page): array
    {
        try {
            $content = $this->extractTextContent($page->json_content);
            $analysis = $this->performAnalysis($page, $content);
            
            // Store analysis results
            SeoLog::create([
                'page_id' => $page->id,
                'analysis_results' => $analysis,
                'seo_score' => $analysis['overall_score'],
                'issues_found' => $analysis['issues'],
                'recommendations' => $analysis['recommendations'],
                'keywords_analysis' => $analysis['keywords'],
                'analyzed_at' => now(),
                'analyzer_version' => '1.0'
            ]);

            // Update page SEO data
            $page->update([
                'seo_data' => [
                    'score' => $analysis['overall_score'],
                    'last_analyzed' => now()->toIso8601String(),
                    'critical_issues' => count(array_filter($analysis['issues'], fn($issue) => $issue['severity'] === 'critical'))
                ]
            ]);

            return $analysis;

        } catch (\Exception $e) {
            Log::error('SEO analysis failed', [
                'page_id' => $page->id,
                'error' => $e->getMessage()
            ]);

            return [
                'overall_score' => 0,
                'issues' => [['type' => 'analysis_error', 'message' => 'Analysis failed', 'severity' => 'critical']],
                'recommendations' => [],
                'keywords' => []
            ];
        }
    }

    /**
     * Perform comprehensive SEO analysis.
     */
    protected function performAnalysis(Page $page, string $content): array
    {
        $issues = [];
        $recommendations = [];
        $score = 100;

        // 1. Title Analysis
        if (empty($page->title)) {
            $issues[] = ['type' => 'missing_title', 'message' => 'Page title is missing', 'severity' => 'critical'];
            $score -= 20;
        } elseif (strlen($page->title) > 60) {
            $issues[] = ['type' => 'title_too_long', 'message' => 'Page title is too long (over 60 characters)', 'severity' => 'warning'];
            $score -= 5;
        } elseif (strlen($page->title) < 30) {
            $recommendations[] = ['type' => 'title_length', 'message' => 'Consider making your title longer (30-60 characters)'];
        }

        // 2. Meta Description Analysis
        if (empty($page->meta_description)) {
            $issues[] = ['type' => 'missing_meta_description', 'message' => 'Meta description is missing', 'severity' => 'high'];
            $score -= 15;
        } elseif (strlen($page->meta_description) > 160) {
            $issues[] = ['type' => 'meta_description_too_long', 'message' => 'Meta description is too long (over 160 characters)', 'severity' => 'warning'];
            $score -= 5;
        } elseif (strlen($page->meta_description) < 120) {
            $recommendations[] = ['type' => 'meta_description_length', 'message' => 'Consider making your meta description longer (120-160 characters)'];
        }

        // 3. Content Analysis
        $wordCount = str_word_count($content);
        if ($wordCount < 300) {
            $issues[] = ['type' => 'low_content', 'message' => "Content is too short ({$wordCount} words). Aim for at least 300 words.", 'severity' => 'medium'];
            $score -= 10;
        }

        // 4. Heading Analysis
        $headings = $this->extractHeadings($page->json_content);
        if (empty($headings['h1'])) {
            $issues[] = ['type' => 'missing_h1', 'message' => 'Missing H1 heading', 'severity' => 'high'];
            $score -= 15;
        } elseif (count($headings['h1']) > 1) {
            $issues[] = ['type' => 'multiple_h1', 'message' => 'Multiple H1 headings found. Use only one H1 per page.', 'severity' => 'medium'];
            $score -= 8;
        }

        // 5. Image Analysis
        $images = $this->extractImages($page->json_content);
        $imagesWithoutAlt = array_filter($images, fn($img) => empty($img['alt']));
        if (!empty($imagesWithoutAlt)) {
            $count = count($imagesWithoutAlt);
            $issues[] = ['type' => 'missing_alt_text', 'message' => "{$count} image(s) missing alt text", 'severity' => 'medium'];
            $score -= min($count * 2, 10);
        }

        // 6. Internal Links Analysis
        $internalLinks = $this->extractInternalLinks($page->json_content);
        if (count($internalLinks) < 2) {
            $recommendations[] = ['type' => 'internal_links', 'message' => 'Consider adding more internal links to improve SEO'];
        }

        // 7. Keywords Analysis
        $keywords = $this->analyzeKeywords($content, $page->meta_keywords ?? []);

        return [
            'overall_score' => max(0, $score),
            'issues' => $issues,
            'recommendations' => $recommendations,
            'keywords' => $keywords,
            'content_stats' => [
                'word_count' => $wordCount,
                'heading_count' => array_sum(array_map('count', $headings)),
                'image_count' => count($images),
                'internal_links' => count($internalLinks)
            ],
            'analyzed_at' => now()->toIso8601String()
        ];
    }

    /**
     * Extract text content from JSON structure.
     */
    protected function extractTextContent(array $jsonContent): string
    {
        $text = '';
        
        foreach ($jsonContent as $section) {
            if (isset($section['content'])) {
                if (is_string($section['content'])) {
                    $text .= strip_tags($section['content']) . ' ';
                } elseif (is_array($section['content'])) {
                    $text .= $this->extractTextContent($section['content']);
                }
            }
            
            if (isset($section['text'])) {
                $text .= strip_tags($section['text']) . ' ';
            }
            
            if (isset($section['title'])) {
                $text .= strip_tags($section['title']) . ' ';
            }
        }
        
        return trim($text);
    }

    /**
     * Extract headings from JSON structure.
     */
    protected function extractHeadings(array $jsonContent): array
    {
        $headings = ['h1' => [], 'h2' => [], 'h3' => [], 'h4' => [], 'h5' => [], 'h6' => []];
        
        foreach ($jsonContent as $section) {
            if (isset($section['type']) && in_array($section['type'], ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'])) {
                $headings[$section['type']][] = $section['content'] ?? $section['text'] ?? '';
            }
            
            if (isset($section['content']) && is_array($section['content'])) {
                $subHeadings = $this->extractHeadings($section['content']);
                foreach ($subHeadings as $level => $texts) {
                    $headings[$level] = array_merge($headings[$level], $texts);
                }
            }
        }
        
        return $headings;
    }

    /**
     * Extract images from JSON structure.
     */
    protected function extractImages(array $jsonContent): array
    {
        $images = [];
        
        foreach ($jsonContent as $section) {
            if (isset($section['type']) && $section['type'] === 'image') {
                $images[] = [
                    'src' => $section['src'] ?? '',
                    'alt' => $section['alt'] ?? '',
                    'title' => $section['title'] ?? ''
                ];
            }
            
            if (isset($section['content']) && is_array($section['content'])) {
                $images = array_merge($images, $this->extractImages($section['content']));
            }
        }
        
        return $images;
    }

    /**
     * Extract internal links from JSON structure.
     */
    protected function extractInternalLinks(array $jsonContent): array
    {
        $links = [];
        
        foreach ($jsonContent as $section) {
            if (isset($section['type']) && $section['type'] === 'link') {
                $href = $section['href'] ?? '';
                if ($this->isInternalLink($href)) {
                    $links[] = $href;
                }
            }
            
            if (isset($section['content']) && is_array($section['content'])) {
                $links = array_merge($links, $this->extractInternalLinks($section['content']));
            }
        }
        
        return array_unique($links);
    }

    /**
     * Analyze keywords in content.
     */
    protected function analyzeKeywords(string $content, array $targetKeywords): array
    {
        $words = str_word_count(strtolower($content), 1);
        $wordCount = array_count_values($words);
        
        // Remove common stop words
        $stopWords = ['the', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by', 'is', 'are', 'was', 'were', 'be', 'been', 'have', 'has', 'had', 'do', 'does', 'did', 'will', 'would', 'could', 'should'];
        $wordCount = array_diff_key($wordCount, array_flip($stopWords));
        
        // Sort by frequency
        arsort($wordCount);
        
        $analysis = [
            'total_words' => count($words),
            'unique_words' => count($wordCount),
            'top_keywords' => array_slice($wordCount, 0, 10, true),
            'keyword_density' => []
        ];

        // Analyze target keywords
        foreach ($targetKeywords as $keyword) {
            $keyword = strtolower($keyword);
            $occurrences = substr_count(strtolower($content), $keyword);
            $density = $occurrences > 0 ? ($occurrences / count($words)) * 100 : 0;
            
            $analysis['keyword_density'][$keyword] = [
                'occurrences' => $occurrences,
                'density' => round($density, 2)
            ];
        }

        return $analysis;
    }

    /**
     * Check if a link is internal.
     */
    protected function isInternalLink(string $href): bool
    {
        return !empty($href) && 
               !str_starts_with($href, 'http') && 
               !str_starts_with($href, 'mailto:') && 
               !str_starts_with($href, 'tel:');
    }
}



