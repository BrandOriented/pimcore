<?php
declare(strict_types=1);

/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

namespace Pimcore\Bundle\CoreBundle\EventListener\Traits;

use Pimcore\Bundle\AdminBundle\Controller\AdminAbstractController;
use Pimcore\Controller\FrontendController;
use Pimcore\Http\Request\Resolver\PimcoreContextResolver;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Service\Attribute\Required;

/**
 * @internal
 */
trait PimcoreContextAwareTrait
{
    private ?PimcoreContextResolver $pimcoreContextResolver = null;

    #[Required]
    public function setPimcoreContextResolver(PimcoreContextResolver $contextResolver): void
    {
        $this->pimcoreContextResolver = $contextResolver;
    }

    /**
     * Check if the request matches the given pimcore context (e.g. admin)
     *
     *
     */
    protected function matchesPimcoreContext(Request $request, array|string $context): bool
    {
        if (null === $this->pimcoreContextResolver) {
            throw new RuntimeException('Missing pimcore context resolver. Is the listener properly configured?');
        }

        return $this->pimcoreContextResolver->matchesPimcoreContext($request, $context);
    }

    public function isPimcoreController(Request $request): bool
    {
        $controller = $this->getControllerName($request);
        if($controller) {
            $controller = new \ReflectionClass('\\' . $controller);
            return $controller->isSubclassOf(FrontendController::class) ||
                $controller->isSubclassOf(AdminAbstractController::class) ||
                $controller->isSubclassOf(\Pimcore\Controller\Controller::class);


        }
        return false;
    }

    public function getControllerName(Request $request): ?string
    {
        $controller = $request->attributes->get('_controller');
        if($controller !== null) {
            $controller = explode('::', $controller);

            // use this line if you want to remove the trailing "Controller" string
            //return isset($controller[4]) ? preg_replace('/Controller$/', '', $controller[4]) : false;

            if (isset($controller[0])) {
                if (str_contains($controller[0], '\\')) {
                    return $controller[0];
                }
            }
        }

        return null;
    }
}
