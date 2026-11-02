<?php declare(strict_types=1);

// Who uses something: the namespace, class and method (or function) of a row, shared by the reports of the state and of the SQL
function usedBy(string $className, string $methodName): array
{
    $isFunction = $className === '';
    $parts = explode('\\', $isFunction ? $methodName : $className);
    $shortName = array_pop($parts);
    return [
        'namespace' => implode('\\', $parts) ?: '[Global]',
        'class_name' => $className,
        'class_short_name' => $isFunction ? '' : $shortName,
        'method_short_name' => $isFunction ? $shortName : $methodName,
        'method_fqn' => $isFunction ? $methodName : $className . '::' . $methodName,
        'kind' => $isFunction ? 'Function' : 'Method',
    ];
}
