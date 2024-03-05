<?php

namespace HeimrichHannot\FieldpaletteBundle\Util;

use Contao\Controller;
use Throwable;

class Polyfill
{
    /**
     * Retrieves an array from a dca config (in most cases eval) in the following priorities:.
     *
     * 1. The value associated to $array[$property]
     * 2. The value retrieved by $array[$property . '_callback'] which is a callback array like ['Class', 'method'] or ['service.id', 'method']
     * 3. The value retrieved by $array[$property . '_callback'] which is a function closure array like ['Class', 'method']
     *
     * @return mixed|null The value retrieved in the way mentioned above or null
     *
     * @internal https://github.com/heimrichhannot/contao-utils-bundle/blob/ee122d2e267a60aa3200ce0f40d92c22028988e8/src/Dca/DcaUtil.php#L375
     */
    public static function getConfigByArrayOrCallbackOrFunction(array $array, $property, array $arguments = []): mixed
    {
        if (isset($array[$property])) {
            return $array[$property];
        }

        $callback = $array[$property.'_callback'] ?? null;

        if (!$callback) {
            return null;
        }

        try
        {
            if (is_array($callback))
            {
                if (!isset($callback[0]) || !isset($callback[1])) {
                    return null;
                }

                $instance = Controller::importStatic($callback[0]);

                if (!method_exists($instance, $callback[1])) {
                    return null;
                }

                return call_user_func_array([$instance, $callback[1]], $arguments);
            }

            if (is_callable($callback))
            {
                return call_user_func_array($callback, $arguments);
            }
        }
        catch (Throwable) {}

        return null;
    }
}