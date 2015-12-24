# PHP-Image-Handler 
A php script to handle the images and thumbnails on my homepage monoclecat.de

### The following is the content from the [project page on my homepage](http://www.monoclecat.de/?l=image-handler) ###

When I write articles I like to add lots of pictures so readers can get a better idea of the project I'm writing about. On average this results in ~5 images. Before I wrote the image handler, I would have to choose the images I want to add, then resize them in GIMP two times (for two different sizes of thumbnails). After that, I would need to upload them all under different names and in the article, copy and paste the url of the image into the html link tag.
This got annoying very quickly


### How the image handler works ###

The image handler is a php script that gives you a form to upload one or more image files and at the same time assign them a tag. Every image is then saved in an "originals" directory. This image is then automatically resized to 6 different sizes, every possibly needed thumbnail is generated this way.
The original image, along with its thumbnails, is given a "Group ID" and every image and thumbnail itself is given its own unique ID. 

### Inserting a thumbnail into an article ###

To insert a thumbnail into an article, all you need is the unique ID. You then call the function "imgtag" with the ID as an argument. The function generates the html code which you can directly echo onto the html page. "imgtag" even has the ability to add a link to the thumbnail, which opens up the original image it was created from. 
