<?php

namespace SilverStripe\S3\Adapter;

use SilverStripe\Assets\Flysystem\ProtectedAdapter;

class ProtectedCachedAdapter extends AbstractCacheAdapter implements ProtectedAdapter
{
    public function getProtectedUrl($path)
    {
        return $this->getAdapter()->getProtectedUrl($path);
    }
}
