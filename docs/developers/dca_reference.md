# Dca reference

The most attributes listed in the [DCA Reference](https://docs.contao.org/dev/reference/dca/) are supported. Additional
attributes will be listed here.

## Config

```php
$dca['fields']['additionalAddresses']['fieldpalette']['config'] = [
    //...
];
```

| Key           | Value                 | Description                                                   |
|---------------|-----------------------|---------------------------------------------------------------|
| hidePublished | bool (default: false) | Hide published palette (added by default)                     |
| table         | string                | Use a custom table instead of the fieldpalette default table. |

## Listing records

```php
$dca['fields']['additionalAddresses']['fieldpalette']['list'] = [
    //...
];
```

### Sorting

```php
$dca['fields']['additionalAddresses']['fieldpalette']['list']['sorting'] = [
    //...
];
```

| Key      | Value            | Description                                   |
|----------|------------------|-----------------------------------------------|
| viewMode | int (default: 0) | View mode <br />**0** Table <br /> **1** List |