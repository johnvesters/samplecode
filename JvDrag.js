$(document).on ("dragstart", ".columns .column", function (event) {
   $(this).css ('opacity', '0.5');
   
   event.originalEvent.dataTransfer.effectAllowed = 'move';
   event.originalEvent.dataTransfer.setData('text', event.target.id);
});

$(document).on ("dragend", ".columns .column", function (event) {
   event.preventDefault();
   $(this).css('opacity', '1');
});

$(document).on ("dragleave", ".target", function (event) {
   $(this).removeClass ("dragover");
});

$(document).on ("dragenter", ".target", function (event) {
   event.preventDefault();
   if ($(this).find (".column").length == 0) {
      $(this).addClass ("dragover");
   }
});

$(document).on ("dragover", ".columns", function (event) {
   event.preventDefault();
});

$(document).on ("drop", ".source", function (event) {
   event.preventDefault();

   var l_Object = $(".columns").find ("#" + event.originalEvent.dataTransfer.getData('text'));
   var l_insertPoint = $(this).closest ("div.JvC_editPanel").find ("div.columns[data-name=" + event.originalEvent.dataTransfer.getData('text') + "]");
   l_Object.appendTo (l_insertPoint);
});

$(document).on ("drop", ".target", function (event) {
   event.preventDefault();
   $(this).removeClass ("dragover");

   if ($(this).find (".column").length == 0) {
      var l_Object = $(".columns").find ("#" + event.originalEvent.dataTransfer.getData('text'));
      l_Object.appendTo ($(this));
      l_Object.find ("input").prop("name", $(this).data ("defColumn"));
   }
});