<?php

namespace test\eLife\DummyApi;

use PHPUnit_Framework_TestCase;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\Request;
use Traversable;

final class SmokeTest extends PHPUnit_Framework_TestCase
{
    use SilexTestCase;

    /**
     * @test
     */
    public function it_can_be_pinged()
    {
        $response = $this->getApp()->handle(Request::create('/ping'));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('pong', $response->getContent());
        $this->assertSame('text/plain; charset=UTF-8', $response->headers->get('Content-Type'));
        $this->assertSame('must-revalidate, no-cache, no-store, private', $response->headers->get('Cache-Control'));
    }

    /**
     * @test
     * @dataProvider requestProvider
     */
    public function it_returns_valid_responses(Request $request, $contentType, int $statusCode = 200)
    {
        $response = $this->getApp()->handle($request);

        if (in_array('--debug', $_SERVER['argv'], true) && $response->getStatusCode() === 500) {
            $json = json_decode($response->getContent(), true);
            if (isset($json['exception'])) {
                $this->fail($json['exception']);
            }
            $this->fail($json);
        }

        $this->assertSame($statusCode, $response->getStatusCode(), $response->getContent());
        if (is_array($contentType)) {
            $this->assertContains($response->headers->get('Content-Type'), $contentType);
        } else {
            $this->assertSame($contentType, $response->headers->get('Content-Type'));
        }
        if (strpos('+json', $response->headers->get('Content-Type'))) {
            $this->assertTrue(is_array(json_decode($response->getContent(), true)), 'Does not contain a JSON response');
        }
    }

    public function requestProvider() : Traversable
    {
        yield $path = '/' => [
            Request::create($path),
            'application/problem+json',
            404,
        ];

        yield '/annual-reports/2012 wrong version' => [
            Request::create('/annual-reports/2012', 'GET', [], [], [],
                ['HTTP_ACCEPT' => 'application/vnd.elife.annual-report+json; version=2']),
            'application/problem+json',
            406,
        ];

        yield $path = '/annual-reports' => [
            Request::create($path),
            'application/vnd.elife.annual-report-list+json; version=1',
        ];
        foreach ((new Finder())->files()->name('*.json')->in(__DIR__.'/../data/annual-reports') as $file) {
            yield $path = '/annual-reports/'.$file->getBasename('.json') => [
                Request::create($path),
                'application/vnd.elife.annual-report+json; version=1',
            ];
        }

        yield $path = '/articles' => [
            Request::create($path),
            'application/vnd.elife.article-list+json; version=1',
        ];
        foreach ((new Finder())->files()->name('*.json')->in(__DIR__.'/../data/articles') as $file) {
            yield $path = '/articles/'.$file->getBasename('.json') => [
                Request::create($path),
                [
                    'application/vnd.elife.article-poa+json; version=1',
                    'application/vnd.elife.article-vor+json; version=1',
                ],
            ];
            yield $path = '/articles/'.$file->getBasename('.json').'/versions' => [
                Request::create($path),
                'application/vnd.elife.article-history+json; version=1',
            ];
            yield $path = '/articles/'.$file->getBasename('.json').'/versions/1' => [
                Request::create($path),
                [
                    'application/vnd.elife.article-poa+json; version=1',
                    'application/vnd.elife.article-vor+json; version=1',
                ],
            ];
            yield $path = '/articles/'.$file->getBasename('.json').'/related' => [
                Request::create($path),
                'application/vnd.elife.article-related+json; version=1',
            ];
        }

        yield $path = '/blog-articles' => [
            Request::create($path),
            'application/vnd.elife.blog-article-list+json; version=1',
        ];
        foreach ((new Finder())->files()->name('*.json')->in(__DIR__.'/../data/blog-articles') as $file) {
            yield $path = '/blog-articles/'.$file->getBasename('.json') => [
                Request::create($path),
                'application/vnd.elife.blog-article+json; version=1',
            ];
        }

        yield $path = '/collections' => [
            Request::create($path),
            'application/vnd.elife.collection-list+json; version=1',
        ];
        foreach ((new Finder())->files()->name('*.json')->in(__DIR__.'/../data/collections') as $file) {
            yield $path = '/collections/'.$file->getBasename('.json') => [
                Request::create($path),
                'application/vnd.elife.collection+json; version=1',
            ];
        }

        yield $path = '/community' => [
            Request::create($path),
            'application/vnd.elife.community-list+json; version=1',
        ];

        yield $path = '/covers' => [
            Request::create($path),
            'application/vnd.elife.cover-list+json; version=1',
        ];
        yield $path = '/covers?start-date=2017-01-01&end-date=2017-01-01' => [
            Request::create($path),
            'application/vnd.elife.cover-list+json; version=1',
        ];
        yield $path = '/covers?start-date=2017-02-29' => [
            Request::create($path),
            'application/problem+json',
            400,
        ];
        yield $path = '/covers?end-date=2017-02-29' => [
            Request::create($path),
            'application/problem+json',
            400,
        ];
        yield $path = '/covers?start-date=2017-01-02&end-date=2017-01-01' => [
            Request::create($path),
            'application/problem+json',
            400,
        ];
        yield $path = '/covers/current' => [
            Request::create($path),
            'application/vnd.elife.cover-list+json; version=1',
        ];

        yield $path = '/events' => [
            Request::create($path),
            'application/vnd.elife.event-list+json; version=1',
        ];
        foreach ((new Finder())->files()->name('*.json')->in(__DIR__.'/../data/events') as $file) {
            yield $path = '/events/'.$file->getBasename('.json') => [
                Request::create($path),
                'application/vnd.elife.event+json; version=1',
            ];
        }

        yield $path = '/labs-posts' => [
            Request::create($path),
            'application/vnd.elife.labs-post-list+json; version=1',
        ];
        foreach ((new Finder())->files()->name('*.json')->in(__DIR__.'/../data/labs') as $file) {
            yield $path = '/labs-posts/'.$file->getBasename('.json') => [
                Request::create($path),
                'application/vnd.elife.labs-post+json; version=1',
            ];
        }

        yield $path = '/medium-articles' => [
            Request::create($path),
            'application/vnd.elife.medium-article-list+json; version=1',
        ];

        foreach ((new Finder())->files()->name('*.json')->in(__DIR__.'/../data/metrics') as $file) {
            $parts = explode('-', $file->getBasename('.json'));

            yield $path = '/metrics/'.$parts[0].'/'.$parts[1].'/citations' => [
                Request::create($path),
                'application/vnd.elife.metric-citations+json; version=1',
            ];
            yield $path = '/metrics/'.$parts[0].'/'.$parts[1].'/downloads' => [
                Request::create($path),
                'application/vnd.elife.metric-time-period+json; version=1',
            ];
            yield $path = '/metrics/'.$parts[0].'/'.$parts[1].'/page-views' => [
                Request::create($path),
                'application/vnd.elife.metric-time-period+json; version=1',
            ];
        }

        yield $path = '/people' => [
            Request::create($path),
            'application/vnd.elife.person-list+json; version=1',
        ];
        foreach ((new Finder())->files()->name('*.json')->in(__DIR__.'/../data/people') as $file) {
            yield $path = '/people/'.$file->getBasename('.json') => [
                Request::create($path),
                'application/vnd.elife.person+json; version=1',
            ];
        }

        yield $path = '/podcast-episodes' => [
            Request::create($path),
            'application/vnd.elife.podcast-episode-list+json; version=1',
        ];
        foreach ((new Finder())->files()->name('*.json')->in(__DIR__.'/../data/podcast-episodes') as $file) {
            yield $path = '/podcast-episodes/'.$file->getBasename('.json') => [
                Request::create($path),
                'application/vnd.elife.podcast-episode+json; version=1',
            ];
        }

        yield $path = '/press-packages' => [
            Request::create($path),
            'application/vnd.elife.press-package-list+json; version=1',
        ];
        foreach ((new Finder())->files()->name('*.json')->in(__DIR__.'/../data/press-packages') as $file) {
            yield $path = '/press-packages/'.$file->getBasename('.json') => [
                Request::create($path),
                'application/vnd.elife.press-package+json; version=1',
            ];
        }

        yield $path = '/subjects' => [
            Request::create($path),
            'application/vnd.elife.subject-list+json; version=1',
        ];
        foreach ((new Finder())->files()->name('*.json')->in(__DIR__.'/../data/subjects') as $file) {
            yield $path = '/subjects/'.$file->getBasename('.json') => [
                Request::create($path),
                'application/vnd.elife.subject+json; version=1',
            ];
        }

        yield $path = '/search' => [
            Request::create($path),
            'application/vnd.elife.search+json; version=1',
        ];
        yield $path = '/search?for=cell' => [
            Request::create($path),
            'application/vnd.elife.search+json; version=1',
        ];
        yield $path = '/search?subject[]=cell-biology' => [
            Request::create($path),
            'application/vnd.elife.search+json; version=1',
        ];
        yield $path = '/search?start-date=2017-01-01&end-date=2017-01-01' => [
            Request::create($path),
            'application/vnd.elife.search+json; version=1',
        ];
        yield $path = '/search?start-date=2017-02-29' => [
            Request::create($path),
            'application/problem+json',
            400,
        ];
        yield $path = '/search?end-date=2017-02-29' => [
            Request::create($path),
            'application/problem+json',
            400,
        ];
        yield $path = '/search?start-date=2017-01-02&end-date=2017-01-01' => [
            Request::create($path),
            'application/problem+json',
            400,
        ];
    }
}
