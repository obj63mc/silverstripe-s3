<?php

namespace SilverStripe\S3\Adapter;

use SilverStripe\Assets\Flysystem\PublicAdapter;

class PublicCachedAdapter extends AbstractCacheAdapter implements PublicAdapter
{
    public function getPublicUrl($path)
    {
        return $this->getAdapter()->getPublicUrl($path);
    }
}
