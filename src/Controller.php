<?php

declare(strict_types=1);

namespace Webdevchampion\BoltformaddonExtension;

use Bolt\Extension\ExtensionController;
use Symfony\Component\HttpFoundation\Response;

class Controller extends ExtensionController
{
    public function index($name = 'foo'): Response
    {
        $context = [
            'title' => 'Boltform Addon Extension',
            'name' => $name,
        ];

        return $this->render('@boltformaddon-extension/page.html.twig', $context);
    }
}
