<?php

namespace App\Controller;

/**
 * Minimal placeholder ModuleDebugController.
 *
 * Reason: the services loader expects a class named App\Controller\ModuleDebugController
 * to exist at this path when importing services from the src/ resource. Previously
 * this file was modified to avoid a duplicate class declaration. That caused the
 * loader error in some deployments where the file exists but the expected class
 * wasn't present. Restoring a minimal class here resolves the LoaderLoadException.
 */
final class ModuleDebugController
{
    // Intentionally empty. The actual debug controller implementation lives at
    // src/Controller/Debug/ModuleDebugController.php. This placeholder prevents
    // service import errors while preserving previous removal of duplicate logic.
}
