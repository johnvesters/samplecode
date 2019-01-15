Date.prototype.getDayName = function() {
  var dayNames = new Array ('Zondag', 'Maandag', 'Dinsdag', 'Woensdag', 'Donderdag', 'Vrijdag', 'Zaterdag');
  return dayNames[this.getDay()];
};

Date.prototype.getMonthName = function() {
   var monthNames = new Array('Jan', 'Feb', 'Maart', 'April', 'Mei', 'Juni', 'Juli', 'Aug', 'Sept', 'Okt', 'Nov', 'Dec');
   return monthNames[this.getMonth()];
};

Date.prototype.ddmmyyyyFormat = function() {
   return (this.getDate () + ' ' + this.getMonthName () + ' ' + this.getFullYear ());
};

Date.prototype.yyyymmddFormat = function() {
   var month = this.getMonth () + 1;
   return (this.getFullYear () + "-" + ("0" + month).slice (-2) + "-" + ("0" + this.getDate ()).slice (-2));
};

function getDateFromWeek (year, week, dayOfWeek) {
   var weekMinusOne = week - 1;
   var dayOfWeekMinusOne = dayOfWeek - 1;

   // 4th of January always in week 1 (NEN2772)
   var d = new Date (Date.UTC (year, 0, 4));
   // Find offset from Monday (pos or neg)
   var dayNumber = d.getDay ();
   if (dayNumber == 0) dayNumber = 7;
   dayNumber -= 5;
   var offset = (weekMinusOne * 7) - dayNumber;

   d.setDate (offset + dayOfWeekMinusOne);
   return (d);
};

$(document).on ("click", "input[type=password] + i.fa-eye", function(event) {
   $(this).prev ("input").prop ("type", "text");
});

$(document).on ("click touchstart", "div.nav ul>i", function(event) {
   var ul = $(this);
   if (ul.hasClass ("fa-plus-square-o")) {
      ul.removeClass ("fa-plus-square-o").addClass ("fa-minus-square-o");
   } else {
      ul.removeClass ("fa-minus-square-o").addClass ("fa-plus-square-o");
   }
   ul.parent ("ul").children ("li, ul").toggle ();
});

$(document).on ("click touchstart", "div.nav ul>i + h4", function(event) {
   var ul = $(this).prev ("i");
   if (ul.hasClass ("fa-plus-square-o")) {
      ul.removeClass ("fa-plus-square-o").addClass ("fa-minus-square-o");
   } else {
      ul.removeClass ("fa-minus-square-o").addClass ("fa-plus-square-o");
   }
   ul.parent ("ul").children ("li, ul").toggle ();
});

$(document).on ("keyup", "[data-track-change=yes]", function (event) {
   if (event.which == 10 || event.which == 13) { // [ENTER]
      var value = encodeURIComponent ($(this).val());
      if (typeof(Storage) !== undefined && $(this).data ("uuidStored") !== undefined) {
         localStorage.setItem($(this).data ("uuidStored"), value);
      }
      $("a[data-action=load].enabled").data ("uuid", value);
      $("a[data-action=load].enabled").click ();
      return false;
   }
});

$(document).on ("blur", "[data-track-change=yes]", function (event) {
   var value = encodeURIComponent ($(this).val());
   if (typeof(Storage) !== undefined && $(this).data ("uuidStored") !== undefined) {
      localStorage.setItem($(this).data ("uuidStored"), value);
   }
   $("a[data-action=load].enabled").data ("uuid", value);
});

$(document).on ("click touchend", "input[data-track-change=yes] + i.fa-refresh", function (event) {
   event.stopImmediatePropagation();
   $("a[data-action=load].enabled").click ();
});
