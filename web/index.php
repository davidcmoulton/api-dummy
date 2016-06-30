<?php

use Crell\ApiProblem\ApiProblem;
use Negotiation\Accept;
use Negotiation\Negotiator;
use Silex\Application;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

require_once __DIR__ . '/../vendor/autoload.php';

$app = new Application();

$app['articles'] = function () use ($app) {
    $finder = (new Finder())->files()->name('*.json')->in(__DIR__ . '/../data/articles');

    $articles = [];
    foreach ($finder as $file) {
        $json = json_decode($file->getContents(), true);
        $articles[$json['versions'][0]['id']] = $json;
    }

    uasort($articles, function (array $article1, array $article2) {
        $article1Dates = [
            'poa' => null,
            'vor' => null,
        ];

        $article2Dates = [
            'poa' => null,
            'vor' => null,
        ];

        foreach ($article1['versions'] as $version) {
            if (null === $article1Dates[$version['status']]) {
                $article1Dates[$version['status']] = DateTimeImmutable::createFromFormat(DATE_ATOM,
                    $version['published']);
            }
        }

        foreach ($article2['versions'] as $version) {
            if (null === $article2Dates[$version['status']]) {
                $article2Dates[$version['status']] = DateTimeImmutable::createFromFormat(DATE_ATOM,
                    $version['published']);
            }
        }

        $article1Date = $article1Dates['vor'] ?? $article1Dates['poa'];
        $article2Date = $article2Dates['vor'] ?? $article2Dates['poa'];

        return $article1Date <=> $article2Date;
    });

    return $articles;
};

$app['experiments'] = function () use ($app) {
    $finder = (new Finder())->files()->name('*.json')->in(__DIR__ . '/../data/experiments');

    $experiments = [];
    foreach ($finder as $file) {
        $json = json_decode($file->getContents(), true);
        $experiments[(int) $json['number']] = $json;
    }

    ksort($experiments);

    return $experiments;
};

$app['negotiator'] = function () {
    return new Negotiator();
};

$app->get('/articles', function (Request $request) use ($app) {
    $accepts = [
        'application/vnd.elife.article-list+json; version=1'
    ];

    $type = $app['negotiator']->getBest($request->headers->get('Accept'), $accepts);

    if (null === $type) {
        $type = new Accept($accepts[0]);
    }

    $version = (int) $type->getParameter('version');
    $type = $type->getType();

    $articles = $app['articles'];

    $page = $request->query->get('page', 1);
    $perPage = $request->query->get('per-page', 10);

    $content = [
        'total' => count($articles),
        'items' => [],
    ];

    if ('desc' === $request->query->get('order', 'desc')) {
        $articles = array_reverse($articles);
    }

    if ($request->query->has('subject')) {
        $articles = array_filter($articles, function (array $article) use ($request) : bool {
            $latestVersion = $article['versions'][count($article['versions']) - 1];

            return count(array_intersect((array) $request->query->get('subject'), $latestVersion['subjects']));
        });
    }

    $articles = array_slice($articles, ($page * $perPage) - $perPage, $perPage);

    if (0 === count($articles) && $page > 1) {
        throw new NotFoundHttpException('No page ' . $page);
    }

    foreach ($articles as $i => $article) {
        $latestVersion = $article['versions'][count($article['versions']) - 1];

        unset($latestVersion['issue']);
        unset($latestVersion['copyright']);
        unset($latestVersion['researchOrganisms']);
        unset($latestVersion['keywords']);
        unset($latestVersion['relatedArticles']);
        unset($latestVersion['abstract']);
        unset($latestVersion['digest']);
        unset($latestVersion['content']);

        $content['items'][] = $latestVersion;
    }

    $headers = ['Content-Type' => sprintf('%s; version=%s', $type, $version)];

    return new Response(
        json_encode($content, JSON_PRETTY_PRINT),
        Response::HTTP_OK,
        $headers
    );
});

$app->get('/articles/{number}',
    function (Request $request, string $number) use ($app) {
        if (false === isset($app['articles'][$number])) {
            throw new NotFoundHttpException('Article not found');
        };

        $latestVersion = count($app['articles'][$number]['versions']);

        return $app->redirect('/articles/' . $number . '/versions/' . $latestVersion); // TODO serve response directory
    }
);

$app->get('/articles/{number}/versions',
    function (Request $request, string $number) use ($app) {
        if (false === isset($app['articles'][$number])) {
            throw new NotFoundHttpException('Article not found');
        };

        $article = $app['articles'][$number];

        $accepts = [
            'application/vnd.elife.article-history+json; version=1'
        ];

        $type = $app['negotiator']->getBest($request->headers->get('Accept'), $accepts);

        if (null === $type) {
            $type = new Accept($accepts[0]);
        }

        $version = (int) $type->getParameter('version');
        $type = $type->getType();

        $content = [
            'received' => $article['received'],
            'accepted' => $article['accepted'],
            'poa' => 0,
            'vor' => 0,
        ];

        foreach ($article['versions'] as $articleVersion) {
            $content[$articleVersion['status']]++;
        }

        return new Response(
            json_encode($content, JSON_PRETTY_PRINT),
            Response::HTTP_OK,
            ['Content-Type' => sprintf('%s; version=%s', $type, $version)]
        );
    }
);

$app->get('/articles/{number}/versions/{version}',
    function (Request $request, string $number, int $version) use ($app) {
        if (false === isset($app['articles'][$number])) {
            throw new NotFoundHttpException('Article not found');
        };

        $article = $app['articles'][$number];

        if (false === isset($article['versions'][$version - 1])) {
            throw new NotFoundHttpException('Version not found');
        };

        $articleVersion = $article['versions'][$version - 1];

        if ('vor' === $articleVersion['status']) {
            $accepts = [
                'application/vnd.elife.article-vor+json; version=1'
            ];
        } else {
            $accepts = [
                'application/vnd.elife.article-poa+json; version=1'
            ];
        }

        unset($articleVersion['status']);

        $type = $app['negotiator']->getBest($request->headers->get('Accept'), $accepts);

        if (null === $type) {
            $type = new Accept($accepts[0]);
        }

        $version = (int) $type->getParameter('version');
        $type = $type->getType();

        return new Response(
            json_encode($articleVersion, JSON_PRETTY_PRINT),
            Response::HTTP_OK,
            ['Content-Type' => sprintf('%s; version=%s', $type, $version)]
        );
    }
);

$app->get('/labs-experiments', function (Request $request) use ($app) {
    $accepts = [
        'application/vnd.elife.labs-experiment-list+json; version=1'
    ];

    $type = $app['negotiator']->getBest($request->headers->get('Accept'), $accepts);

    if (null === $type) {
        $type = new Accept($accepts[0]);
    }

    $version = (int) $type->getParameter('version');
    $type = $type->getType();

    $experiments = $app['experiments'];

    $page = $request->query->get('page', 1);
    $perPage = $request->query->get('per-page', 10);

    $content = [
        'total' => count($experiments),
        'items' => [],
    ];

    if ('desc' === $request->query->get('order', 'desc')) {
        $experiments = array_reverse($experiments);
    }

    $experiments = array_slice($experiments, ($page * $perPage) - $perPage, $perPage);

    if (0 === count($experiments) && $page > 1) {
        throw new NotFoundHttpException('No page ' . $page);
    }

    foreach ($experiments as $i => $experiment) {
        unset($experiment['content']);

        $content['items'][$i] = $experiment;
    }

    $headers = ['Content-Type' => sprintf('%s; version=%s', $type, $version)];

    if ($request->query->get('foo')) {
        $headers['Warning'] = '299 elifesciences.org "Deprecation: `foo` query string parameter will be removed, use `bar` instead"';
    }

    return new Response(
        json_encode($content, JSON_PRETTY_PRINT),
        Response::HTTP_OK,
        $headers
    );
});

$app->get('/labs-experiments/{number}',
    function (Request $request, int $number) use ($app) {
        if (false === isset($app['experiments'][$number])) {
            throw new NotFoundHttpException('Not found');
        };

        $experiment = $app['experiments'][$number];

        $accepts = [
            'application/vnd.elife.labs-experiment+json; version=1'
        ];

        $type = $app['negotiator']->getBest($request->headers->get('Accept'), $accepts);

        if (null === $type) {
            $type = new Accept($accepts[0]);
        }

        $version = (int) $type->getParameter('version');
        $type = $type->getType();

        return new Response(
            json_encode($experiment, JSON_PRETTY_PRINT),
            Response::HTTP_OK,
            ['Content-Type' => sprintf('%s; version=%s', $type, $version)]
        );
    })->assert('number', '[1-9][0-9]*');

$app->error(function (Throwable $e) {
    if ($e instanceof HttpExceptionInterface) {
        $status = $e->getStatusCode();
        $message = $e->getMessage();
        $extra = [];
    } else {
        $status = Response::HTTP_INTERNAL_SERVER_ERROR;
        $message = 'Error';
        $extra = [
            'exception' => $e->getMessage(),
            'stacktrace' => $e->getTraceAsString()
        ];
    }

    $problem = new ApiProblem($message);

    foreach ($extra as $key => $value) {
        $problem[$key] = $value;
    }

    return new Response(
        json_encode(json_decode($problem->asJson()), JSON_PRETTY_PRINT),
        $status,
        ['Content-Type' => 'application/problem+json']
    );
});

$app->run();
