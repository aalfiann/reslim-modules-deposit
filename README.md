### Detail module information

1. Namespace >> **modules/deposit**
2. Zip Archive source >> 
    https://github.com/aalfiann/reSlim-modules-deposit/archive/master.zip


### How to integrate this module into reSlim?

1. Download zip then upload to reSlim server to the **modules/**
2. Extract zip then you will get new folder like **reSlim-modules-deposit-master**
3. Rename foldername **reSlim-modules-deposit-master** to **deposit**
4. Done

### How to integrate this module into reSlim with Packager?

1. Make AJAX GET request to >>
    http://**{yourdomain.com}**/api/packager/install/zip/safely/**{yourusername}**/**{yourtoken}**/?lang=en&source=**{zip archive source}**&namespace=**{modul namespace}**

### How to integrate this module into database?
This module is require integration to the current database.

1. Make AJAX GET request to >>
    http://**{yourdomain.com}**/api/deposit/install/**{yourusername}**/**{yourtoken}**

### Security Tips
After successful integration database, you must remove the **install** and **uninstall** router.  
Just make some edit in the **deposit.router.php** file manually.