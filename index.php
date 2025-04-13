<?php
// Include the Simple HTML DOM library
require_once 'simple_html_dom.php';

// Function to scrape website content
function scrapeWebsite($url, $selector, $type) {
    $results = [];
    $html = file_get_html($url);
    
    if (!$html) {
        return ['error' => 'Failed to load the URL. Please check if the URL is valid and accessible.'];
    }
    
    switch ($type) {
        case 'title':
            $results[] = $html->find('title', 0)->plaintext;
            break;
            
        case 'meta':
            // Get meta description
            $meta_desc = $html->find('meta[name=description]', 0);
            if ($meta_desc) {
                $results[] = [
                    'type' => 'description',
                    'content' => $meta_desc->content
                ];
            }
            
            // Get meta keywords
            $meta_keywords = $html->find('meta[name=keywords]', 0);
            if ($meta_keywords) {
                $results[] = [
                    'type' => 'keywords',
                    'content' => $meta_keywords->content
                ];
            }
            
            // Get Open Graph tags
            foreach ($html->find('meta[property^=og:]') as $og) {
                $results[] = [
                    'type' => 'og:' . str_replace('og:', '', $og->property),
                    'content' => $og->content
                ];
            }
            
            // Get Twitter Card tags
            foreach ($html->find('meta[name^=twitter:]') as $twitter) {
                $results[] = [
                    'type' => $twitter->name,
                    'content' => $twitter->content
                ];
            }
            
            // Get canonical URL
            $canonical = $html->find('link[rel=canonical]', 0);
            if ($canonical) {
                $results[] = [
                    'type' => 'canonical',
                    'content' => $canonical->href
                ];
            }
            break;
            
        case 'headings':
            // Get all headings H1-H6
            for ($i = 1; $i <= 6; $i++) {
                foreach ($html->find('h' . $i) as $heading) {
                    $results[] = [
                        'type' => 'h' . $i,
                        'text' => trim($heading->plaintext),
                        'html' => $heading->outertext
                    ];
                }
            }
            break;
            
        case 'links':
            foreach ($html->find('a') as $link) {
                $href = $link->href;
                
                // Convert relative URLs to absolute
                if (strpos($href, 'http') !== 0 && $href && $href[0] !== '#' && strpos($href, 'javascript:') !== 0) {
                    $base_url = parse_url($url, PHP_URL_SCHEME) . '://' . parse_url($url, PHP_URL_HOST);
                    if (strpos($href, '/') === 0) {
                        $href = $base_url . $href;
                    } else {
                        $path = dirname(parse_url($url, PHP_URL_PATH));
                        $href = $base_url . $path . '/' . $href;
                    }
                }
                
                if ($href && strpos($href, 'javascript:') !== 0) {
                    $results[] = [
                        'url' => $href,
                        'text' => trim($link->plaintext),
                        'title' => $link->title,
                        'rel' => $link->rel,
                        'target' => $link->target,
                        'internal' => (strpos($href, parse_url($url, PHP_URL_HOST)) !== false)
                    ];
                }
            }
            break;
            
        case 'keywords':
            // Get page content without scripts, styles, etc.
            $text_content = '';
            foreach ($html->find('p, h1, h2, h3, h4, h5, h6, li') as $element) {
                $text_content .= ' ' . $element->plaintext;
            }
            
            // Clean and normalize text
            $text_content = strtolower($text_content);
            $text_content = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $text_content);
            $text_content = preg_replace('/\s+/', ' ', $text_content);
            
            // Split into words
            $words = explode(' ', $text_content);
            
            // Count word frequency
            $word_count = array_count_values($words);
            
            // Remove common words (stop words)
            $stop_words = ['a', 'an', 'the', 'and', 'or', 'but', 'is', 'are', 'was', 'were', 'be', 'been', 'being', 
                          'in', 'on', 'at', 'to', 'for', 'with', 'by', 'about', 'against', 'between', 'into', 
                          'through', 'during', 'before', 'after', 'above', 'below', 'from', 'up', 'down', 'of', 
                          'off', 'over', 'under', 'again', 'further', 'then', 'once', 'here', 'there', 'when', 
                          'where', 'why', 'how', 'all', 'any', 'both', 'each', 'few', 'more', 'most', 'other', 
                          'some', 'such', 'no', 'nor', 'not', 'only', 'own', 'same', 'so', 'than', 'too', 'very', 
                          'can', 'will', 'just', 'should', 'now', 'this', 'that', 'these', 'those', 'i', 'me', 
                          'my', 'myself', 'we', 'our', 'ours', 'ourselves', 'you', 'your', 'yours', 'yourself', 
                          'yourselves', 'he', 'him', 'his', 'himself', 'she', 'her', 'hers', 'herself', 'it', 
                          'its', 'itself', 'they', 'them', 'their', 'theirs', 'themselves', 'what', 'which', 
                          'who', 'whom', 'whose', 'if', 'as', '', ' '];
            
            foreach ($stop_words as $word) {
                unset($word_count[$word]);
            }
            
            // Remove single character words
            foreach ($word_count as $word => $count) {
                if (mb_strlen($word) <= 2) {
                    unset($word_count[$word]);
                }
            }
            
            // Sort by frequency
            arsort($word_count);
            
            // Take top 50 keywords
            $word_count = array_slice($word_count, 0, 50, true);
            
            foreach ($word_count as $word => $count) {
                $results[] = [
                    'keyword' => $word,
                    'count' => $count,
                    'density' => round(($count / count($words)) * 100, 2) . '%'
                ];
            }
            break;
            
        case 'schema':
            // Find JSON-LD structured data
            foreach ($html->find('script[type="application/ld+json"]') as $script) {
                $json = json_decode($script->innertext, true);
                if ($json) {
                    $results[] = [
                        'type' => 'json-ld',
                        'data' => $json
                    ];
                }
            }
            
            // Find microdata
            $microdata = [];
            foreach ($html->find('[itemscope]') as $element) {
                $item = [
                    'type' => $element->itemtype,
                    'properties' => []
                ];
                
                foreach ($element->find('[itemprop]') as $prop) {
                    $item['properties'][] = [
                        'name' => $prop->itemprop,
                        'content' => $prop->content ?: $prop->plaintext
                    ];
                }
                
                $microdata[] = $item;
            }
            
            if (!empty($microdata)) {
                $results[] = [
                    'type' => 'microdata',
                    'data' => $microdata
                ];
            }
            break;
            
        case 'images':
            foreach ($html->find('img') as $img) {
                // Convert relative URLs to absolute
                $src = $img->src;
                if (strpos($src, 'http') !== 0) {
                    $base_url = parse_url($url, PHP_URL_SCHEME) . '://' . parse_url($url, PHP_URL_HOST);
                    if (strpos($src, '/') === 0) {
                        $src = $base_url . $src;
                    } else {
                        $path = dirname(parse_url($url, PHP_URL_PATH));
                        $src = $base_url . $path . '/' . $src;
                    }
                }
                
                $results[] = [
                    'src' => $src,
                    'alt' => $img->alt,
                    'width' => $img->width,
                    'height' => $img->height
                ];
            }
            break;
            
        case 'tables':
            foreach ($html->find('table') as $index => $table) {
                $tableData = [];
                
                // Get headers
                $headers = [];
                foreach ($table->find('th') as $th) {
                    $headers[] = trim($th->plaintext);
                }
                
                // Get rows
                $rows = [];
                foreach ($table->find('tr') as $tr) {
                    $row = [];
                    foreach ($tr->find('td') as $td) {
                        $row[] = trim($td->plaintext);
                    }
                    if (!empty($row)) {
                        $rows[] = $row;
                    }
                }
                
                $tableData = [
                    'headers' => $headers,
                    'rows' => $rows
                ];
                
                $results[] = $tableData;
            }
            break;
            
        case 'custom':
            foreach ($html->find($selector) as $element) {
                $results[] = [
                    'html' => $element->outertext,
                    'text' => $element->plaintext
                ];
            }
            break;
    }
    
    // Clean up
    $html->clear();
    unset($html);
    
    return $results;
}

// Process AJAX requests
if (isset($_POST['action']) && $_POST['action'] === 'scrape') {
    header('Content-Type: application/json');
    
    $url = isset($_POST['url']) ? $_POST['url'] : '';
    $selector = isset($_POST['selector']) ? $_POST['selector'] : '';
    $type = isset($_POST['type']) ? $_POST['type'] : '';
    $page = isset($_POST['page']) ? (int)$_POST['page'] : 1;
    
    if (empty($url)) {
        echo json_encode(['error' => 'URL is required']);
        exit;
    }
    
    try {
        $results = scrapeWebsite($url, $selector, $type);
        
        // Paginate results for lazy loading
        $itemsPerPage = 10;
        $totalItems = count($results);
        $totalPages = ceil($totalItems / $itemsPerPage);
        
        $paginatedResults = array_slice($results, ($page - 1) * $itemsPerPage, $itemsPerPage);
        
        echo json_encode([
            'results' => $paginatedResults,
            'pagination' => [
                'page' => $page,
                'totalPages' => $totalPages,
                'totalItems' => $totalItems
            ]
        ]);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Web Scraper Tool</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="styles.css" rel="stylesheet">
    <!-- Google Fonts -->
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Fira+Code:wght@400;500&display=swap" rel="stylesheet">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 sidebar">
                <div class="sidebar-sticky">
                    <h3 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1">
                        <span><i class="fas fa-spider me-2"></i>Web Scraper</span>
                    </h3>
                    <div class="px-3 py-2">
                        <form id="scrapeForm">
                            <div class="mb-3">
                                <label for="url" class="form-label">Website URL</label>
                                <input type="url" class="form-control" id="url" name="url" placeholder="https://example.com" required>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">What to scrape?</label>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="scrapeType" id="scrapeTitle" value="title" checked>
                                    <label class="form-check-label" for="scrapeTitle">
                                        <i class="fas fa-heading me-1"></i> Page Title
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="scrapeType" id="scrapeMeta" value="meta">
                                    <label class="form-check-label" for="scrapeMeta">
                                        <i class="fas fa-tags me-1"></i> Meta Tags (SEO)
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="scrapeType" id="scrapeHeadings" value="headings">
                                    <label class="form-check-label" for="scrapeHeadings">
                                        <i class="fas fa-heading me-1"></i> All Headings (H1-H6)
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="scrapeType" id="scrapeLinks" value="links">
                                    <label class="form-check-label" for="scrapeLinks">
                                        <i class="fas fa-link me-1"></i> All Links
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="scrapeType" id="scrapeKeywords" value="keywords">
                                    <label class="form-check-label" for="scrapeKeywords">
                                        <i class="fas fa-key me-1"></i> Keywords Analysis
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="scrapeType" id="scrapeSchema" value="schema">
                                    <label class="form-check-label" for="scrapeSchema">
                                        <i class="fas fa-code me-1"></i> Structured Data (Schema)
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="scrapeType" id="scrapeImages" value="images">
                                    <label class="form-check-label" for="scrapeImages">
                                        <i class="fas fa-images me-1"></i> All Images
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="scrapeType" id="scrapeTables" value="tables">
                                    <label class="form-check-label" for="scrapeTables">
                                        <i class="fas fa-table me-1"></i> All Tables
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="scrapeType" id="scrapeCustom" value="custom">
                                    <label class="form-check-label" for="scrapeCustom">
                                        <i class="fas fa-code me-1"></i> Custom Selector
                                    </label>
                                </div>
                            </div>
                            
                            <div class="mb-3" id="selectorContainer" style="display: none;">
                                <label for="selector" class="form-label">CSS Selector</label>
                                <input type="text" class="form-control" id="selector" name="selector" placeholder="div.class, #id, tag">
                                <small class="form-text text-muted">
                                    Examples: <code>div.content</code>, <code>#main-content</code>, <code>h1</code>
                                </small>
                            </div>
                            
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-search me-2"></i>Scrape Now
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Web Scraping Results</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="exportCSV">
                                <i class="fas fa-file-csv me-1"></i> Export CSV
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="exportJSON">
                                <i class="fas fa-file-code me-1"></i> Export JSON
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Loading indicator -->
                <div id="loadingIndicator" class="text-center py-5" style="display: none;">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2">Scraping data, please wait...</p>
                </div>
                
                <!-- Error message -->
                <div id="errorMessage" class="alert alert-danger" style="display: none;"></div>
                
                <!-- Results container -->
                <div id="resultsContainer"></div>
                
                <!-- Load more button -->
                <div id="loadMoreContainer" class="text-center my-4" style="display: none;">
                    <button id="loadMoreBtn" class="btn btn-outline-primary">
                        <i class="fas fa-sync me-2"></i>Load More
                    </button>
                </div>
            </main>
        </div>
    </div>
    
    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Custom JS -->
    <script src="script.js"></script>
    <!-- Particles.js -->
<script src="https://cdn.jsdelivr.net/npm/particles.js@2.0.0/particles.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Add particles container
        const particlesContainer = document.createElement('div');
        particlesContainer.id = 'particles-js';
        document.body.prepend(particlesContainer);
        
        // Initialize particles
        particlesJS('particles-js', {
            "particles": {
                "number": {
                    "value": 80,
                    "density": {
                        "enable": true,
                        "value_area": 800
                    }
                },
                "color": {
                    "value": "#3a86ff"
                },
                "shape": {
                    "type": "circle",
                    "stroke": {
                        "width": 0,
                        "color": "#000000"
                    },
                    "polygon": {
                        "nb_sides": 5
                    }
                },
                "opacity": {
                    "value": 0.3,
                    "random": false,
                    "anim": {
                        "enable": false,
                        "speed": 1,
                        "opacity_min": 0.1,
                        "sync": false
                    }
                },
                "size": {
                    "value": 3,
                    "random": true,
                    "anim": {
                        "enable": false,
                        "speed": 40,
                        "size_min": 0.1,
                        "sync": false
                    }
                },
                "line_linked": {
                    "enable": true,
                    "distance": 150,
                    "color": "#3a86ff",
                    "opacity": 0.2,
                    "width": 1
                },
                "move": {
                    "enable": true,
                    "speed": 2,
                    "direction": "none",
                    "random": false,
                    "straight": false,
                    "out_mode": "out",
                    "bounce": false,
                    "attract": {
                        "enable": false,
                        "rotateX": 600,
                        "rotateY": 1200
                    }
                }
            },
            "interactivity": {
                "detect_on": "canvas",
                "events": {
                    "onhover": {
                        "enable": true,
                        "mode": "grab"
                    },
                    "onclick": {
                        "enable": true,
                        "mode": "push"
                    },
                    "resize": true
                },
                "modes": {
                    "grab": {
                        "distance": 140,
                        "line_linked": {
                            "opacity": 0.6
                        }
                    },
                    "bubble": {
                        "distance": 400,
                        "size": 40,
                        "duration": 2,
                        "opacity": 8,
                        "speed": 3
                    },
                    "repulse": {
                        "distance": 200,
                        "duration": 0.4
                    },
                    "push": {
                        "particles_nb": 4
                    },
                    "remove": {
                        "particles_nb": 2
                    }
                }
            },
            "retina_detect": true
        });
    });
</script>
</body>
</html>