<?php

declare(strict_types=1);

namespace Lumynus\Framework;

use Lumynus\Http\Contracts\Response;
use Lumynus\Framework\LumynusContainer;

trait Helpers
{

    /**
     * Helper method to dump and debug data.
     * 
     * @param mixed $data 
     * @return void 
     */
    public function dd(...$data): void
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0];

        echo '<pre style="background:#111;color:#0f0;padding:15px;border-radius:8px;">';

        echo "Line {$trace['file']} : {$trace['line']}\n";

        foreach ($data as $item) {
            var_dump($item);
        }

        echo "\nTime: " . round((microtime(true) - $_SERVER["REQUEST_TIME_FLOAT"]), 4) . "s";
        echo "\nMemory: " . round(memory_get_usage() / 1024 / 1024, 2) . " MB";

        echo "</pre>";

        exit;
    }

    /**
     * Retrieves an instance of the ContainerProxy class.
     * @return ContainerProxy Instance of the ContainerProxy class.
     */
    public function container(): ContainerProxy
    {
        // The proxy also remains under container control
        return LumynusContainer::resolve(ContainerProxy::class);
    }
}
