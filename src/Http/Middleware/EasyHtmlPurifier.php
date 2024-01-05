<?php

namespace Nocturnal\EasyHtmlPurifier\Http\Middleware;

use Closure;
use Exception;
use HTMLPurifier;
use HTMLPurifier_AttrDef;
use HTMLPurifier_Config;
use HTMLPurifier_HTMLDefinition;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Contracts\Config\Repository;

class EasyHtmlPurifier extends Authenticate
{
    /**
     * @var HTMLPurifier
     */
    private HTMLPurifier $purifier;

    /**
     * @var Repository
     */
    protected Repository $config;

    /**
     * @param $request
     * @param Closure $next
     * @param ...$guards
     * @return mixed
     * @throws Exception
     */
    public function handle($request, Closure $next, ...$guards): mixed
    {
        if ($this->shouldSanitize($request)) {
            $this->config = new \Illuminate\Config\Repository();
            $configObj = HTMLPurifier_Config::createDefault();
            $this->purifier = new HTMLPurifier($configObj);
            $inputs = $request->all();
            $inputs = $this->sanitizeInputs($inputs);
            $request->merge($inputs);
        }

        return $next($request);
    }

    /**
     * @param $request
     * @return bool
     */
    protected function shouldSanitize($request): bool
    {
        return in_array($request->method(), ['POST', 'PUT', 'PATCH']);
    }

    /**
     * @param $input
     * @return mixed
     */
    private function sanitizeInputs($input): mixed
    {
        if (is_array($input)) return $this->sanitizeArray($input);
        if (is_bool($input)
            || is_numeric($input)
            || is_file($input)
        ) return $input;
        if (is_string($input)) return $this->sanitizeString($input);
        if (is_object($input)) return $this->sanitizeObject($input);
        return $input;
    }

    /**
     * @param array $input
     * @return array
     */
    private function sanitizeArray(array $input): array
    {
        return array_map([$this, 'sanitizeInputs'], $input);
    }

    /**
     * @param $input
     * @return object
     */
    private function sanitizeObject($input): object
    {
        foreach (get_object_vars($input) as $key => $value) {
            $input->$key = $this->sanitizeInputs($value);
        }
        return $input;
    }

    /**
     * @param $input
     * @return string|null
     */
    private function sanitizeString($input): ?string
    {
        $input = $this->clean($input);
        return (empty($input) && $input !== '0') ? null : $input;
    }


    /**
     * @return HTMLPurifier_Config|array
     */
    private function getConfig(): HTMLPurifier_Config|array
    {
        $config = null;
        // Create a new configuration object
        $configObject = HTMLPurifier_Config::createDefault();

        // Allow configuration to be modified
        if (!$this->config->get('html_purifier.finalize')) {
            $configObject->autoFinalize = false;
        }

        // Set default config
        $defaultConfig = [];
        $defaultConfig['Core.Encoding'] = $this->config->get('html_purifier.encoding');
        $defaultConfig['Cache.SerializerPath'] = $this->config->get('html_purifier.cachePath');
        $defaultConfig['Cache.SerializerPermissions'] = $this->config->get('html_purifier.cacheFileMode', 0755);

        if (!(null)) {
            $config = $this->config->get('html_purifier.settings.default');
        } elseif (is_string($config)) {
            $config = $this->config->get('html_purifier.settings.' . $config);
        }

        if (!is_array($config)) {
            $config = [];
        }

        // Merge configurations
        $config = $defaultConfig + $config;

        // Load to Purifier config
        $configObject->loadArray($config);

        // Load custom definition if set
        if ($definitionConfig = $this->config->get('html_purifier.settings.custom_definition')) {
            $this->addCustomDefinition($definitionConfig, $configObject);
        }

        // Load custom elements if set
        if ($elements = $this->config->get('html_purifier.settings.custom_elements')) {
            if ($def = $configObject->maybeGetRawHTMLDefinition()) {
                $this->addCustomElements($elements, $def);
            }
        }

        // Load custom attributes if set
        if ($attributes = $this->config->get('html_purifier.settings.custom_attributes')) {
            if ($def = $configObject->maybeGetRawHTMLDefinition()) {
                $this->addCustomAttributes($attributes, $def);
            }
        }

        return $configObject;
    }

    /**
     * @param array $elements
     * @param HTMLPurifier_HTMLDefinition $definition
     * @return void
     */
    private function addCustomElements(array $elements, HTMLPurifier_HTMLDefinition $definition): void
    {
        foreach ($elements as $element) {
            // Get configuration of element
            $name = $element[0];
            $contentSet = $element[1];
            $allowedChildren = $element[2];
            $attributeCollection = $element[3];
            $attributes = $element[4] ?? null;

            if (!empty($attributes)) {
                $definition->addElement($name, $contentSet, $allowedChildren, $attributeCollection, $attributes);
            } else {
                $definition->addElement($name, $contentSet, $allowedChildren, $attributeCollection);
            }
        }
    }

    /**
     * @param array $attributes
     * @param HTMLPurifier_HTMLDefinition $definition
     * @return void
     */
    private function addCustomAttributes(array $attributes, HTMLPurifier_HTMLDefinition $definition): void
    {
        foreach ($attributes as $attribute) {
            // Get configuration of attribute
            $required = !empty($attribute[3]);
            $onElement = $attribute[0];
            $attrName = $required ? $attribute[1] . '*' : $attribute[1];
            $validValues = $attribute[2];

            if ($onElement === '*') {
                $def = $validValues;
                if (is_string($validValues)) {
                    $def = new $validValues();
                }

                if ($def instanceof HTMLPurifier_AttrDef) {
                    $definition->info_global_attr[$attrName] = $def;
                }

                continue;
            }

            if (class_exists($validValues)) {
                $validValues = new $validValues();
            }

            $definition->addAttribute($onElement, $attrName, $validValues);
        }

    }

    /**
     * @param array $definitionConfig
     * @param HTMLPurifier_Config|null $configObject
     * @return void
     */
    private function addCustomDefinition(array $definitionConfig, HTMLPurifier_Config $configObject = null): void
    {
        if (!$configObject) {
            $configObject = HTMLPurifier_Config::createDefault();
            $configObject->loadArray($this->getConfig());
        }

        // Set up the custom definition
        $configObject->set('HTML.DefinitionID', $definitionConfig['id']);
        $configObject->set('HTML.DefinitionRev', $definitionConfig['rev']);

        // Enable debug mode
        if (!isset($definitionConfig['debug']) || $definitionConfig['debug']) {
            $configObject->set('Cache.DefinitionImpl', null);
        }

        // Start configuring the definition
        if ($def = $configObject->maybeGetRawHTMLDefinition()) {
            // Create the definition attributes
            if (!empty($definitionConfig['attributes'])) {
                $this->addCustomAttributes($definitionConfig['attributes'], $def);
            }

            // Create the definition elements
            if (!empty($definitionConfig['elements'])) {
                $this->addCustomElements($definitionConfig['elements'], $def);
            }
        }

    }

    /**
     * @param $dirty
     * @return string
     */
    private function clean($dirty): string
    {
        //If $dirty is not an explicit string, bypass purification assuming configuration allows this
        $ignoreNonStrings = $this->config->get('html_purifier.ignoreNonStrings', false);
        $stringTest = is_string($dirty);
        if ($stringTest === false && $ignoreNonStrings === true) {
            return $dirty;
        }

        return $this->purifier->purify($dirty);
    }
}
