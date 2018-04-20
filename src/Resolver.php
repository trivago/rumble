<?php

namespace Rumble;

use Aws\Credentials\Credentials;

trait Resolver
{
    /**
     *  Get class names from files in migrations/seeds directory.
     *  For any class found require it, so we can create an instance.
     *
     * @param $dir
     * @return array
     * @throws \Exception
     */
    protected function getClasses($dir)
    {
        if (!file_exists($dir)) {
            throw new \Exception("{$dir} directory not found.");
        }

        $dirHandler = opendir($dir);
        $classes = [];
        while (false != ($file = readdir($dirHandler))) {
            if ($file != "." && $file != "..") {
                if (is_dir("{$dir}/{$file}")) {
                    return self::getClasses("{$dir}/{$file}");
                }
                require_once("{$dir}/{$file}");
                $classes[] = $this->buildClass($file);;
            }
        }
        closedir($dirHandler);

        if (count($classes) == 0) {
            throw new \Exception("There are no {$dir} files run.");
        }
        return $classes;
    }

    /**
     *  Build class names from file name. This uses an underscore (_) convention.
     *  Each file in eigther the migrations or seeds folder, uses an underscore naming
     *  convention. eg: create_me_table => CreateMeTable (ClassName)
     *
     * @param $file
     * @return mixed
     */
    protected function buildClass($file)
    {
        $file = basename($file, '.php');
        $fileNameParts = explode('_', $file);

        foreach ($fileNameParts as &$part) {
            $part = ucfirst($part);
        }
        return implode('', $fileNameParts);
    }

    /**
     * @return mixed
     * @throws \Exception
     */
    protected function getConfig()
    {
        return [
            'region' => getenv("AWS_DYNAMO_REGION"),
            'endpoint' => getenv("AWS_DYNAMO_ENDPOINT"),
            'version' => getenv("AWS_DYNAMO_VERSION")
        ];
    }
}
