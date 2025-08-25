add SSH key to clone (go to bago-catalog directory):
```
eval "$(ssh-agent -s)"
ssh-add bedroomcult
```

cloning
```
git clone ssh://git@github.com/bedroomcult/bago-catalog
```

Start the server with PHP built in webserver:
```
cd D:/catalog
php -S 0.0.0.0:80
```
OR with Apache web server:
```
httpd
```