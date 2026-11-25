<?php

declare(strict_types=1);

namespace Lumynus\Templates;

/**
 * Compatibility alias for the framework's existing debug page.
 *
 * @package Lumynus\Templates
 * @deprecated 1.10 Use Lumynus\Framework\ErrorTemplate until removal in 2.0.
 */
class_alias(\Lumynus\Framework\ErrorTemplate::class, \Lumynus\Templates\DebugErrorPage::class);
