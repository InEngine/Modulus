<?php

namespace InEngine\Modulus;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Artisan;

/**
 * The class handles:
 * - The listing of all modules, Active and InActive.
 * - Moving a module to or from the Active and InActive lists and in reverse.
 * - Providing the method for a module to check whether it is Active or InActive.
 *
 * @class Modulus
 */
class Modulus
{
    /**
     * @var string $configFile
     */
    protected string $configFile = 'modules';


    /**
     * Get the name of the Module
     *
     * @public GetModuleName()
     * @return string
     */
    public function getModuleName(): string
    {
        return "Modulus";
    }

    /**
     * Get the Fully Qualified Name of the Module
     *
     * @public getModuleFQN
     * @return string
     */
    public function getModuleFQN(): string
    {
        return "InEngine\Modulus";
    }

    /**
     * Get the Namespace of the Module
     *
     * @public getModuleNamespace
     * @return string
     */
    public function getModuleNamespace(): string
    {
        return $this->getModuleFQN();
    }

    /**
     * Get a Collection or Array of all Modules, Active and InActive
     *
     * @public getAllModules
     * @param bool $asArray (optional) Return an Array instead of a Collection
     * @return Collection|array
     */
    public function getAllModules(bool $asArray = false): Collection | array
    {
        if($asArray){
            return config($this->configFile);
        }
        return collect(config($this->configFile));
    }

    /**
     * Get a Collection or Array of all Active Modules
     *
     * @public getActiveModules
     * @param bool $asArray (optional) Return an Array instead of a Collection
     * @return Collection|array
     */
    public function getActiveModules(bool $asArray = false): Collection | array
    {
        if($asArray){
            return config($this->configFile . '.active');
        }
        return collect(config($this->configFile . '.active'));

    }

    /**
     * Get a Collection or Array of all InActive Modules
     *
     * @public getInActiveModules
     * @param bool $asArray (optional) Return an Array instead of a Collection
     * @return Collection|array
     */
    public function getInActiveModules(bool $asArray = false): Collection | array
    {
        if($asArray){
            return config($this->configFile . '.inactive');
        }
        return collect(config($this->configFile . '.inactive'));

    }


    public function activateModule(string $module, $returnModules = false, $returnModulesAsArray = false): null | array | Collection
    {
        $active = $this->getActiveModules(true);
        $inactive = $this->getInActiveModules();
        $moduleData = null;

        //find the module in the inactive list
        if ($inactive->has($module)) {
            $moduleData = $inactive[$module];
            $inactive->forget($module);
            $active[$module] = $moduleData;
        } else {
            throw new \Exception("Module not listed in InActive Modules list, please install or deactivate the module before trying to activate it", E_NOTICE);
        }

        try {
            $this->writeModulesConfig($active, $inactive->toArray());
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage(), $e->getCode(), $e);
        }

        // This is here to refresh the list from the config
        $this->getInActiveModules();

        if ($returnModules) {
            return $this->getActiveModules();
        }

        if ($returnModulesAsArray) {
            return $this->getActiveModules(true);
        }
        return null;
    }

    public function deactivateModule(string $module, $returnModules = false, $returnModulesAsArray = false): null | array | Collection
    {
        $active = $this->getActiveModules();
        $inactive = $this->getInActiveModules(true);
        $moduleData = null;

        //find the module in the inactive list
        if ($active->has($module)) {
            $moduleData = $active[$module];
            $active->forget($module);
            $inactive[$module] = $moduleData;
        } else {
            throw new \Exception("Module not listed in Active Modules list, please install or activate the module before trying to activate it", E_NOTICE);
        }

        try {
            $this->writeModulesConfig($active->toArray(), $inactive);
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage(), $e->getCode(), $e);
        }

        // This is here to refresh the list from the config
        $this->getActiveModules();

        if ($returnModules) {
            return $this->getInActiveModules();
        }

        if ($returnModulesAsArray) {
            return $this->getInActiveModules(true);
        }
        return null;
    }

    protected function writeModulesConfig(array $active, array $inactive)
    {
        $header = $this->getModulesConfigFileHeader();

        $footer = $this->getModulesConfigFileFooter();

        $activeList = $this->getModulesConfigActiveArray($active);

        $inactiveList = $this->getModulesConfigInactiveArray($inactive);

        $content = $header;

        $content .= $activeList;

        $content .= ',' . PHP_EOL;

        $content .= $inactiveList;

        $content .= $footer;

        try {
            if(File::exists(config_path($this->configFile . '.php'))) {
                File::put(config_path($this->configFile . '.php'), $content);
            }
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage(), $e->getCode(), $e);
        }

        $this->clearConfigCache();
    }

    protected function clearConfigCache(): void
    {
        Artisan::call('config:clear');
    }


    protected function getModulesConfigFileHeader():string
    {
        return "<?php" . PHP_EOL . "return [" . PHP_EOL;
    }

    protected function getModulesConfigFileFooter():string
    {
        return "];" . PHP_EOL . PHP_EOL;
    }

    protected function getModulesConfigActiveArray(array $active): string
    {
        return "\t" . "'active' => " . "\t\t" . str_replace(array('array', '(', ')'), array("\t\t\t", '[', ']'), var_export($active, true));
    }

    protected function getModulesConfigInActiveArray(array $inactive): string
    {
        return "\t" ."'inactive' => " . "\t\t" . str_replace(array('array', '(', ')'), array("\t\t\t", '[', ']'), var_export($inactive, true)) . PHP_EOL;
    }



}
