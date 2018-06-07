(function(window) {
  "use strict";

  var River = { }
  if ('River' in window) {
    River = window.River
  } else {
    window.River = River
  }

  var Common = River.Common = { }

  var MONTHS = [ 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug',
    'Sep', 'Oct', 'Nov', 'Dec' ]

  Common.humanizeTimestamp = function humanizeTimestamp(ts) {
    var now = Date.now(),
        then = new Date(ts * 1000),
        delta = now - then // assumes that `ts` is in past

    var t =
      ('00' + then.getHours()).slice(-2) + ':' +
      ('00' + then.getMinutes()).slice(-2)

    if (delta > 24 * 60 * 60 * 1000) {
      t = MONTHS[then.getMonth()] + ' ' +
          ('00' + then.getDate()).slice(-2) + ' ' + t
    }

    return t
  }

  Common.findMask = function findMask(text, mask, offset) {
    offset = offset || 0
    if (typeof mask === 'string') {
      mask = mask.split('')
    }
    var min = Infinity
    for (var i = 0; i < mask.length; i += 1) {
      var pos = text.indexOf(mask[i], offset)
      if (pos !== -1) {
        if (pos < min) {
          min = pos
        }
      }
    }
    return min
  }

  Common.truncate = function truncate(text, max) {
    max = max || 70
    if (text.length > max) {
      var nextSep = Common.findMask(text, ',.!? ', max)
      if (nextSep === Infinity) {
        nextSep = max
      }
      if (nextSep > max * 1.25) {
        nextSep = Common.findMask(text, ',.!? ', max * 0.75)
      }
      text = text.substr(0, nextSep)
      text += '…'
    }
    return text
  }
})(this)
