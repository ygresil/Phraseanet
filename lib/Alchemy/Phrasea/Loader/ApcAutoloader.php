<?php

/*
 * This file is part of Phraseanet
 *
 * (c) 2005-2012 Alchemy
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Alchemy\Phrasea\Loader;

require_once __DIR__ . '/LoaderStrategy.php';
require_once __DIR__ . '/../../../../vendor/doctrine/common/lib/Doctrine/Common/Cache/Cache.php';
require_once __DIR__ . '/../../../../vendor/doctrine/common/lib/Doctrine/Common/Cache/CacheProvider.php';
require_once __DIR__ . '/../../../../vendor/doctrine/common/lib/Doctrine/Common/Cache/ApcCache.php';

use Alchemy\Phrasea\Loader\LoaderStrategy as CacheStrategy;
use Doctrine\Common\Cache\ApcCache;

/**
 *
 * @license     http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link        www.phraseanet.com
 */
Class ApcAutoloader extends ApcCache implements CacheStrategy
{

    /**
     * {@inheritdoc}
     */
    public function isAvailable()
    {
        return extension_loaded('apc');
    }
}
