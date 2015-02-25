<?php
use App\Services\ShortTermMissionsToWufoo;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Silex\Application;

$app = new Application();

$app->post('/webhooks/zapier/email/short-term-missions', function (Request $request) use ($app) {
    if ($request->headers->get('secret') !== ZAPIER_WEBHOOK_SECRET) new Response('Unauthorized', 401);
    $processor = new ShortTermMissionsToWufoo($app);
    try {
        $processor->processPayload($request->request);
    } catch (Exception $exception) {
        $processor->notify($exception->getMessage());
        return new Response($exception->getMessage(), 500);
    }
    return new Response('Submission Processed!', 201);
});

$app->run();