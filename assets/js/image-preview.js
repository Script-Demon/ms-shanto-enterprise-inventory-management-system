/* Live preview for the product-image field. Shows the chosen file before the
   form is submitted, so a mis-picked photo is caught without a round trip. */
(function () {
  var input = document.getElementById('imageInput');
  var preview = document.getElementById('imagePreview');
  if (!input || !preview) return;

  var original = preview.innerHTML;
  var objectUrl = null;

  input.addEventListener('change', function () {
    if (objectUrl) {
      URL.revokeObjectURL(objectUrl);
      objectUrl = null;
    }

    var file = input.files && input.files[0];
    if (!file || !/^image\//.test(file.type)) {
      preview.innerHTML = original;
      return;
    }

    objectUrl = URL.createObjectURL(file);
    var img = document.createElement('img');
    img.alt = '';
    img.src = objectUrl;
    preview.innerHTML = '';
    preview.appendChild(img);

    // A replacement picture overrides a pending "remove", so untick it.
    var remove = document.getElementById('removeImage');
    if (remove) remove.checked = false;
  });
})();
