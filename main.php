<?php

/**
 * This API module is designed to fetch, process, and rate job postings from LinkedIn. It uses FastAPI to provide an endpoint for getting jobs with specific titles, keywords, and location parameters. The API also utilizes the `rate_text` function from the `docsim.php` module to rate job postings based on the relevance of their descriptions to the provided keywords.
 *
 * Developer: Irfan Ahmad (devirfan.mlka@gmail.com / https://irfan-ahmad.com)
 * Project Owner: Monica Piccinini (monicapiccinini12@gmail.com)
 */

require_once 'vendor/autoload.php';
require_once 'docsim.php';

use GuzzleHttp\Client;
use GuzzleHttp\Promise;
use Psr\Http\Message\ResponseInterface;
use GuzzleHttp\Exception\RequestException;
use Psr\Http\Message\RequestInterface;
use Symfony\Component\DomCrawler\Crawler;
use TextLanguage\TextLanguage;
use TextLanguage\Distance\CosineSimilarity;

$client = new Client();

// A function that takes a URL for LinkedIn jobs search and returns a list of dictionaries containing job title, company name, day posted and URL of the job
function get_job_info(Crawler $card, array $plavras): array {
    $jobTitle = trim($card->filter('h3.base-search-card__title')->text());
    $jobURL = $card->filter('a')->attr('href');
    $location = trim($card->filter('span.job-search-card__location')->text());

    $jobDesc = extractDescription($jobURL);
    
    $companyName = trim($card->filter('h4.base-search-card__subtitle')->text());
    $dayPosted = trim($card->filter('time')->text());
    $rating = rate_job($jobDesc['description'], $plavras);

    return [
        'jobTitle' => $jobTitle,
        'companyName' => $companyName,
        'dayPosted' => $dayPosted,
        'jobURL' => $jobURL,
        'rating' => $rating,
        'location' => $location,
        'jobDesc' => $jobDesc['description']
    ];
}

function get_job_cards(string $url): array {
    global $client;

    $response = $client->request('GET', $url);
    $html = (string) $response->getBody();
    $crawler = new Crawler($html);

    $cards = $crawler->filter('ul.jobs-search__results-list li')->each(function (Crawler $card) {
        return $card;
    });

    return $cards;
}

function extractJobs(array $urls, array $plavras): array {
    global $client;

    $promises = [];
    foreach ($urls as $url) {
        $promises[] = $client->requestAsync('GET', $url);
    }

    $responses = Promise\Utils::settle($promises)->wait();
    $cards = [];

    foreach ($responses as $response) {
        if ($response['state'] === 'fulfilled') {
            $html = (string) $response['value']->getBody();
            $crawler = new Crawler($html);
            $cards = array_merge($cards, $crawler->filter('ul.jobs-search__results-list li')->each(function (Crawler $card) {
                return $card;
            }));
        }
    }

    $total_cards = count($cards);
    $results = [];

    foreach ($cards as $card) {
        $results[] = get_job_info($card, $plavras);
    }

    return [$results, $total_cards];
}

function extractDescription(string $url): array {
    global $client;

    $response = $client->request
    ('GET', $url);
    $html = (string) $response->getBody();
    $crawler = new Crawler($html);
    $description = $crawler->filter('.description__text')->text();
    $description = preg_replace('/\s+/', ' ', $description);

    return [
        'description' => $description
    ];
}

function rate_job(string $description, array $keywords): float {
    $docsim = new DocSim();
    $rating = $docsim->rateText($keywords, $description);
    return $rating;
}


function rate_job_cosine(string $description, array $keywords): float {
    $textLanguage = new TextLanguage();
    $cosineSimilarity = new CosineSimilarity($textLanguage);
    
    $descriptionVector = $textLanguage->getTfIdfVector($description);
    $query = implode(' ', $keywords);
    $keywordsVector = $textLanguage->getTfIdfVector($query);

    $rating = $cosineSimilarity->distance($descriptionVector, $keywordsVector);
    return $rating;
}


function fetch_jobs(string $title, string $location, array $keywords, int $pages = 1): array {
$encodedTitle = urlencode($title);
$encodedLocation = urlencode($location);
$urlTemplate = "https://www.linkedin.com/jobs/search/?keywords=$encodedTitle&location=$encodedLocation&pageNum=%d";
$urls = [];
for ($i = 1; $i <= $pages; $i++) {
    $urls[] = sprintf($urlTemplate, $i);
}

[$results, $totalCards] = extractJobs($urls, $keywords);

usort($results, function ($a, $b) {
    return $b['rating'] <=> $a['rating'];
});

return [
    'jobs' => $results,
    'totalCards' => $totalCards
];
}

$title = 'Data Scientist';
$location = 'New York, NY';
$keywords = ['Python', 'R', 'Machine Learning', 'Data Mining'];
$pages = 1;

$result = fetch_jobs($title, $location, $keywords, $pages);
print_r($result);


