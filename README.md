# About cryptor

Encrypt:

```
cryptor -e <input-file> [ -o <output-file> ]
```

Decrypt:

```
cryptor -d <input-file> [ -o <output-file> ]
```


Note: DO NOT forget your password!



# How to pack to phar?

```
php -d phar.readonly=0 /usr/bin/phar pack -f bin/cryptor.phar src
```