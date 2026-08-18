<?php

declare(strict_types=1);
use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Nowo\SentryBundle\NowoSentryBundle;
use Nowo\HotReloadBundle\NowoHotReloadBundle;
use Nowo\TwigInspectorBundle\NowoTwigInspectorBundle;
use Sentry\SentryBundle\SentryBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Bundle\WebProfilerBundle\WebProfilerBundle;
use Twig\Extra\TwigExtraBundle\TwigExtraBundle;

return [
    FrameworkBundle::class         => ['all' => true],
    SecurityBundle::class          => ['all' => true],
    DoctrineBundle::class          => ['all' => true],
    SentryBundle::class            => ['all' => true],
    NowoSentryBundle::class        => ['all' => true],
    TwigBundle::class              => ['all' => true],
    WebProfilerBundle::class       => ['dev' => true, 'test' => true],
    NowoHotReloadBundle::class => ['dev' => true, 'test' => true],
    NowoTwigInspectorBundle::class => ['dev' => true],
    TwigExtraBundle::class => ['all' => true],
];
