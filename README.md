* Exemplo de uso em qualquer das suas *.conf

```
exten => redial,n,AGI(googletts.php,"teste de voz no asterisk");
exten => redial,n,Wait(2)
```
