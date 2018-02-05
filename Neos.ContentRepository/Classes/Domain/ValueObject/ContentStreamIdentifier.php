<?php

namespace Neos\ContentRepository\Domain\ValueObject;

/*
 * This file is part of the Neos.ContentRepository package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\Cache\CacheAwareInterface;

final class ContentStreamIdentifier extends AbstractIdentifier implements CacheAwareInterface
{
    /**
     * @return string
     */
    public function getCacheEntryIdentifier(): string
    {
        return $this->uuid->toString();
    }
}
