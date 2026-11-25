<?php

declare(strict_types=1);

namespace Lumynus\Framework;

/**
 * Compatibility alias for the debug error page.
 *
 * @package Lumynus\Framework
 * @deprecated 1.10 Use Lumynus\Templates\DebugErrorPage until removal in 2.0.
 */
class_alias(\Lumynus\Templates\DebugErrorPage::class, \Lumynus\Framework\ErrorTemplate::class);
