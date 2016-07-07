@echo off

IF EXIST %~dp0/cryptor.phar (
  php %~dp0/cryptor.phar %*
) ELSE (
  php %~dp0/cryptor %*
)
