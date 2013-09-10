<?php

namespace Gaufrette\Adapter;

use Gaufrette\File;
use Gaufrette\Adapter;

/**
 * ReadThrough adapter - fetches all files from local (main) adpter and only if they cannot be found gets them from the
 * readThrough adapter. eg. in our case local is a gridFs for Dev environments and S3 for readThrough.
 *
 * WARNING: Unfortunately gridFs needs a / at the beginning of the keys - but S3 prefers not to have (incorrect
 * rendering in most GUIs). Thus the hackish '/' appending for our special case. So this adapter is not recommended for
 * other use cases without proper testing first. Also hardly any methods re implemented. Just supports plain read, write
 *
 * @package Gaufrette
 * @author  Oliver Buschjost <oliver.buschjost@feelgood.de>
 */
class ReadThroughToFallback extends Base
{
    /**
     * @var Adapter
     */
    protected $main;

    /**
     * @var Adapter
     */
    protected $readThrough;

    /**
     * Constructor
     *
     * @param  Adapter $main  		   The source adapter that must be cached
     * @param  Adapter $readThrough    The adapter used to cache the source
     */
    public function __construct(Adapter $main, Adapter $readThrough)
    {
        $this->main = $main;
        $this->readThrough = $readThrough;
    }

    /**
     * {@InheritDoc}
     */
    public function read($key)
    {
        if ($this->main->exists('/' . $key)) {
            return $this->main->read('/' . $key);
        } else {
            if (!$this->main->exists('/%deleted%/' . $key)) {
                $content = $this->readThrough->read($key);
                $this->main->write('/' . $key, $content);
                return $content;
            }

            throw new \RuntimeException(sprintf('Could not read the \'%s\' file.', $key));
        }
    }

    /**
     * {@InheritDoc}
     */
    public function rename($key, $new)
    {
        throw new \BadMethodCallException("keys() not implemented for this special adapter, yet.");
    }

    /**
     * {@inheritDoc}
     */
    public function copy($key, $new)
    {
        throw new \BadMethodCallException("keys() not implemented for this special adapter, yet.");
    }

    /**
     * {@InheritDoc}
     */
    public function write($key, $content, array $metadata = null)
    {
        $this->main->write('/' . $key, $content);
        $this->main->delete('/%deleted%/' . $key);
    }

    /**
     * {@InheritDoc}
     */
    public function exists($key)
    {
        $existsLocally = $this->main->exists('/' . $key);
        if (!$existsLocally) {
            $deletedLocally = $this->main->exists('/%deleted%/' . $key); // check if file was explicitly deleted or if we fall through to read

            if (!$deletedLocally) {
                return $this->readThrough->exists($key);  // fall through to file to read
            }
        }

        return $existsLocally;
    }

    /**
     * {@InheritDoc}
     */
    public function mtime($key)
    {
        throw new \BadMethodCallException("mtime() not implemented for this special adapter, yet.");
    }

    /**
     * {@inheritDoc}
     */
    public function checksum($key)
    {
        throw new \BadMethodCallException("mtime() not implemented for this special adapter, yet.");
    }

    /**
     * {@inheritDoc}
     */
    public function keys($prefix = null)
    {
        $localKeys = $this->main->keys('/' . $prefix);
        $remoteKeys = $this->readThrough->keys($prefix);

        $returnKeys = array();
        foreach ($localKeys as $localKey) {
            $returnKeys[] = ltrim($localKey, '/');
        }

        foreach ($remoteKeys as $remoteKey) {
            if (!$this->main->exists('/%deleted%/' . $remoteKey)) {
                $returnKeys[] = $remoteKey;
            }
        }

        return $returnKeys;
    }

    /**
     * Creates a new File instance and returns it
     *
     * @param  string $key
     * @return File
     */
    public function get($key, $filesystem)
    {
        throw new \BadMethodCallException("get() not implemented for this special adapter, yet.");
    }

    /**
     * @return array
     */
    public function listDirectory($directory = '')
    {
        throw new \BadMethodCallException("listDirectory() not implemented for this special adapter, yet.");
    }

    /**
     * {@InheritDoc}
     */
    public function delete($key)
    {
        $this->main->write('/%deleted%/' . $key, 'deleted');
        $this->main->delete('/' . $key);
    }

    /**
     * {@InheritDoc}
     */
    public function supportsMetadata()
    {
        return false;
    }
}
