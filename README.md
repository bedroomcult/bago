add SSH key to clone:
```
eval "$(ssh-agent -s)"
ssh-add bedroomcult
```

cloning
```
git clone ssh://git@github.com/bedroomcult/bago-catalog
```

Start the server:
```
cd D:/catalog
php -S 0.0.0.0:80
```
