<?php
namespace Larastart\Configurable;

trait Configurable
{

    private function configKey()
    {
        return static::class . "." . $this->getKey();
    }

    public function getConfig($key = null, $default = null)
    {
        $config = Configuration::where('key', $this->configKey())->first();
        if ($config) {
            if ($key) {
                return array_get($config->config, $key, $default);
            } else {
                return $config->config;
            }
        }
        return $default;
    }

    public function setConfig($key, $value = null)
    {
        if (!is_array($key)) {
            $key = [$key => $value];
        }

        $config = $this->getConfig();
        if ($config) {
            Configuration::where('key', $this->configKey())->update(['config' => json_encode(array_merge_recursive($config, $key))]);
        } else {
            Configuration::create(['key' => $this->configKey(), 'config' => $key]);
        }
    }

}