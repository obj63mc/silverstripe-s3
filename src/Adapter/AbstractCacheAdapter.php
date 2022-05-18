<?php

namespace SilverStripe\S3\Adapter;

use League\Flysystem\AdapterInterface;
use League\Flysystem\Config;
use Psr\SimpleCache\CacheInterface;
use SilverStripe\Core\Injector\Injector;

abstract class AbstractCacheAdapter implements AdapterInterface {

    protected $adapter;
    protected $cache;

    public function __construct(AdapterInterface $adapter, $cachenamespace) {
        $this->adapter = $adapter;
        $this->cache = Injector::inst()->get(CacheInterface::class.'.'.$cachenamespace);
    }

    public function write($path, $contents, Config $config) {
        $metadata = $this->adapter->write($path, $contents, $config);

        return $this->updateMetadata($path, $metadata);
    }

    /**
    * {@inheritdoc}
    */
    public function writeStream($path, $resource, Config $config) {
        $metadata = $this->adapter->writeStream($path, $resource, $config);

        return $this->updateMetadata($path, $metadata);
    }

    /**
    * {@inheritdoc}
    */
    public function update($path, $contents, Config $config) {
        $metadata = $this->adapter->update($path, $contents, $config);

        return $this->updateMetadata($path, $metadata);
    }

    /**
    * {@inheritdoc}
    */
    public function updateStream($path, $resource, Config $config) {
        $metadata = $this->adapter->updateStream($path, $resource, $config);

        return $this->updateMetadata($path, $metadata);
    }

    /**
    * {@inheritdoc}
    */
    public function rename($path, $newpath) {
        $result = $this->adapter->rename($path, $newpath);

        if ($result) {
            $item = $this->cache->get($this->cachekey($path));
            if($item){
                $this->cache->set($this->cachekey($newpath), $item);
            }
            $this->cache->delete($this->cachekey($path));
        }

        return $result;
    }

    /**
    * {@inheritdoc}
    */
    public function copy($path, $newpath) {
        $result = $this->adapter->copy($path, $newpath);

        if ($result) {
            if($this->cache->has($this->cachekey($path))){
                $this->cache->set($this->cachekey($newpath), $this->cache->get($this->cachekey($path)));
            }
        }

        return $result;
    }

    /**
    * {@inheritdoc}
    */
    public function delete($path) {
        $result = $this->adapter->delete($path);

        if ($result) {
            $this->cache->delete($this->cachekey($path));
        }

        return $result;
    }

    /**
    * {@inheritdoc}
    */
    public function deleteDir($dirname) {
        // Before the delete we need to know what files are in the directory.
        $contents = $this->adapter->listContents($dirname, TRUE);

        $result = $this->adapter->deleteDir($dirname);

        if ($result) {
            $paths = array_column($contents, 'path');
            foreach($paths as $path){
                $this->cache->delete($this->cachekey($path));
            }
        }

        return $result;
    }

    /**
    * {@inheritdoc}
    */
    public function createDir($dirname, Config $config) {
        $metadata = $this->adapter->createDir($dirname, $config);

        // Warm the metadata cache.
        if ($metadata) {
            $this->cache->set($this->cachekey($dirname), $metadata);
        }

        return $metadata;
    }

    /**
    * {@inheritdoc}
    */
    public function setVisibility($path, $visibility) {
        $metadata = $this->adapter->setVisibility($path, $visibility);

        return $this->updateMetadata($path, $metadata);
    }

    /**
    * {@inheritdoc}
    */
    public function has($path) {
        if ($this->cache->has($this->cachekey($path))) {
            return TRUE;
        }

        // Always check the upstream adapter for new files.
        // TODO: This could be a good place for a microcache?
        return $this->adapter->has($path);
    }

    /**
    * {@inheritdoc}
    */
    public function read($path) {
        return $this->adapter->read($path);
    }

    /**
    * {@inheritdoc}
    */
    public function readStream($path) {
        return $this->adapter->readStream($path);
    }

    /**
    * {@inheritdoc}
    */
    public function listContents($directory = '', $recursive = FALSE) {
        // Don't cache directory listings to avoid having to keep track of
        // incomplete cache entries.
        // TODO: This could be a good place for a microcache?
        return $this->adapter->listContents($directory, $recursive);
    }

    /**
    * {@inheritdoc}
    */
    public function getMetadata($path) {
        $item = $this->cache->get($this->cachekey($path));

        if ($item) {
            return $metadata;
        }

        $metadata = $this->adapter->getMetadata($path);
        $this->updateMetadata($path, $metadata);
        return $metadata;
    }

    /**
    * {@inheritdoc}
    */
    public function getSize($path) {
        return $this->fetchMetadataKey($path, 'size');
    }

    /**
    * {@inheritdoc}
    */
    public function getMimetype($path) {
        return $this->fetchMetadataKey($path, 'mimetype');
    }

    /**
    * {@inheritdoc}
    */
    public function getTimestamp($path) {
        return $this->fetchMetadataKey($path, 'timestamp');
    }

    /**
    * {@inheritdoc}
    */
    public function getVisibility($path) {
        return $this->fetchMetadataKey($path, 'visibility');
    }

    /**
    * Fetches a specific key from metadata.
    *
    * @param string $path
    *   The path to load metadata for.
    * @param string $key
    *   The key in metadata, such as 'mimetype', to load metadata for.
    *
    * @return array
    *   The array of metadata.
    */
    protected function fetchMetadataKey($path, $key) {
        $metadata = $this->cache->get($this->cachekey($path));

        if ($metadata  && isset($metadata[$key])) {
            return $metadata;
        }

        $method = 'get' . ucfirst($key);

        return $this->updateMetadata($path, $this->adapter->$method($path));
    }

    /**
    * Updates the metadata for a given path.
    *
    * @param string $path
    *   The path of file file or directory.
    * @param array|false $metadata
    *   The metadata to update.
    *
    * @return array|false
    *   Returns the value passed in as metadata.
    */
    protected function updateMetadata($path, $metadata) {
        if (!empty($metadata)) {
            $item = $this->cache->get($this->cachekey($path));
            if($item && is_array($item)){
                $item = array_merge($item, $metadata);
            } else {
                $item = $metadata;
            }
            $this->cache->set($this->cachekey($path), $item);
        }

        return $metadata;
    }

    protected function cachekey($path){
        return md5($path);
    }

}
