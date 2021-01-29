<?php

namespace BinaryTorch\LaRecipe\Traits;

use Illuminate\Support\Facades\Log;
use Symfony\Component\DomCrawler\Crawler;

trait Indexable
{
    /**
     * @return mixed
     */
    public function index()
    {
        return $this->cache->remember(function () {
            $pages = $this->getPages();

            $result = [];
            foreach($pages as $page) {
                preg_match('/(?<=docs\/)(.*)(?=\/)/', $page, $versionMatches);
                $version = explode("/", $versionMatches[0])[0];

                $page = substr($page, strpos($page, '/', 6));

                $pageContent = $this->get($version, $page);

                if(! $pageContent)
                    continue;

                $indexableNodes = implode(',', config('larecipe.search.engines.internal.index'));
                
                $nodes = (new Crawler($pageContent))
                        ->filter($indexableNodes)
                        ->each(function (Crawler $node, $i) {
                            return $node->text();
                        });

                $title = (new Crawler($pageContent))
                        ->filter('h1')
                        ->each(function (Crawler $node, $i) {
                            return $node->text();
                        });
                
                $result[] = [
                    'version'  => $version,
                    'path'     => $page,
                    'title'    => $title ? $title[0] : '',
                    'headings' => $nodes
                ];
            }

            return $result;
        }, 'larecipe.docs.search');
    }

    /**
     * @param  $version
     * @return mixed
     */
    protected function getPages()
    {
        $versions = config('larecipe.versions.published');

        $paths = [];
        foreach ($versions as $version) {
            array_push($paths, base_path(config('larecipe.docs.path') . '/' . $version . '/index.md'));
        }

        $allMatches = [];
        foreach ($paths as $path) {
            preg_match('/(?<=docs\/)(.*)(?=\/)/', $path, $versionMatches);
            $version = $versionMatches[0];

            preg_match_all('/\(([^)#]+)\)/', $this->files->get($path), $matches);

            $links = [];
            foreach ($matches[1] as $match) {
                array_push($links, str_replace('{{version}}', $version, $match));
            }
            array_push($allMatches, $links);
        }

        return array_unique(array_merge(...$allMatches));
    }
}
