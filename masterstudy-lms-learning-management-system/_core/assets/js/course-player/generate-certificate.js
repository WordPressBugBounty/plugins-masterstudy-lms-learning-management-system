"use strict";

var jsPDF = window.jspdf.jsPDF;
(function ($) {
  $(document).ready(function () {
    if (course_certificate.preview && !course_certificate.demo) {
      getCertificate(course_certificate.course_id, course_certificate.course_id, true);
    }
    $('body').on('click', '.masterstudy-single-course-complete__buttons .masterstudy-button, .masterstudy_preview_certificate, .masterstudy-student-course-card .masterstudy-button', function (e) {
      e.preventDefault();
      if (course_certificate.demo) {
        return;
      }
      var courseId = '';
      var id = false;
      if (typeof $(this).attr('data-id') !== 'undefined') {
        id = $(this).attr('data-id');
      }
      if (typeof $(this).attr('data-id') !== 'undefined') {
        courseId = $(this).attr('data-id');
      }
      if (id || courseId) {
        getCertificate(id, courseId);
      }
    });
  });
  function getCertificate(id) {
    var courseId = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : '';
    var preview = arguments.length > 2 && arguments[2] !== undefined ? arguments[2] : false;
    var url = course_certificate.ajax_url + '?action=stm_get_certificate&nonce=' + course_certificate.nonce + '&post_id=' + id + '&course_id=' + courseId;
    if (course_certificate.user_id) {
      url += "&user_id=".concat(course_certificate.user_id);
    }
    if (preview) {
      $('.masterstudy-page-certificate__download .masterstudy-button').addClass('masterstudy-button_loading');
    }
    $.ajax({
      url: url,
      method: 'get',
      success: function success(data) {
        if (typeof data.data !== 'undefined') {
          generateCertificate(data.data, preview);
        }
      }
    });
  }
  function generateCertificate(data, preview) {
    var orientation = data.orientation;
    var doc = new jsPDF({
      orientation: orientation,
      unit: 'px',
      format: [600, 900]
    });
    doc.addFileToVFS('OpenSans-Regular-normal.ttf', openSansRegular);
    doc.addFont('OpenSans-Regular-normal.ttf', 'OpenSans', 'normal');
    doc.addFileToVFS('OpenSans-Bold-normal.ttf', openSansBold);
    doc.addFont('OpenSans-Bold-normal.ttf', 'OpenSans', 'bold');
    doc.addFileToVFS('OpenSans-BoldItalic-normal.ttf', openSansBoldItalic);
    doc.addFont('OpenSans-BoldItalic-normal.ttf', 'OpenSans', 'bolditalic');
    doc.addFileToVFS('OpenSans-Italic-italic.ttf', openSansItalic);
    doc.addFont('OpenSans-Italic-italic.ttf', 'OpenSans', 'italic');
    doc.addFileToVFS('Montserrat-normal.ttf', montserratRegular);
    doc.addFont('Montserrat-normal.ttf', 'Montserrat', 'normal');
    doc.addFileToVFS('Montserrat-bold.ttf', montserratBold);
    doc.addFont('Montserrat-bold.ttf', 'Montserrat', 'bold');
    doc.addFileToVFS('Montserrat-italic.ttf', montserratItalic);
    doc.addFont('Montserrat-italic.ttf', 'Montserrat', 'italic');
    doc.addFileToVFS('Montserrat-bolditalic.ttf', montserratBoldItalic);
    doc.addFont('Montserrat-bolditalic.ttf', 'Montserrat', 'bolditalic');
    doc.addFileToVFS('Merriweather-normal.ttf', merriweatherRegular);
    doc.addFont('Merriweather-normal.ttf', 'Merriweather', 'normal');
    doc.addFileToVFS('Merriweather-bold.ttf', merriweatherBold);
    doc.addFont('Merriweather-bold.ttf', 'Merriweather', 'bold');
    doc.addFileToVFS('Merriweather-italic.ttf', merriweatherItalic);
    doc.addFont('Merriweather-italic.ttf', 'Merriweather', 'italic');
    doc.addFileToVFS('Merriweather-bolditalic.ttf', merriweatherBoldItalic);
    doc.addFont('Merriweather-bolditalic.ttf', 'Merriweather', 'bolditalic');
    doc.addFileToVFS('Katibeh-normal.ttf', katibeh);
    doc.addFont('Katibeh-normal.ttf', 'Katibeh', 'normal');
    doc.addFont('Katibeh-normal.ttf', 'Katibeh', 'bold');
    doc.addFont('Katibeh-normal.ttf', 'Katibeh', 'italic');
    doc.addFont('Katibeh-normal.ttf', 'Katibeh', 'bolditalic');
    doc.addFileToVFS('Amiri-normal.ttf', Amiri);
    doc.addFont('Amiri-normal.ttf', 'Amiri', 'normal');
    doc.addFont('Amiri-normal.ttf', 'Amiri', 'bold');
    doc.addFont('Amiri-normal.ttf', 'Amiri', 'italic');
    doc.addFont('Amiri-normal.ttf', 'Amiri', 'bolditalic');
    doc.addFileToVFS('Oswald-normal.ttf', oswald);
    doc.addFont('Oswald-normal.ttf', 'Oswald', 'normal');
    doc.addFont('Oswald-normal.ttf', 'Oswald', 'italic');
    doc.addFileToVFS('Oswald-bold.ttf', oswaldBold);
    doc.addFont('Oswald-bold.ttf', 'Oswald', 'bold');
    doc.addFont('Oswald-bold.ttf', 'Oswald', 'bolditalic');
    doc.addFileToVFS("MPLUS2-normal.ttf", MPLUS2);
    doc.addFont("MPLUS2-normal.ttf", "MPLUS2", "normal");
    doc.addFont("MPLUS2-normal.ttf", "MPLUS2", "bold");
    doc.addFont("MPLUS2-normal.ttf", "MPLUS2", "italic");
    doc.addFont("MPLUS2-normal.ttf", "MPLUS2", "bolditalic");
    var background = data.thumbnail;
    if (background) {
      if (orientation === 'portrait') {
        doc.addImage(background, "JPEG", 0, 0, 600, 900, '', 'NONE');
        if (preview) {
          $('.masterstudy-page-certificate__preview').addClass('masterstudy-page-certificate__preview_vertical');
        }
      } else {
        doc.addImage(background, "JPEG", 0, 0, 900, 600, '', 'NONE');
      }
    }
    data.fields = data.fields.sort(function (a, b) {
      return (a.z || 2) - (b.z || 2);
    });
    var imagePromises = data.fields.map(function (field) {
      if (!field.content) return Promise.resolve(null);
      if (field.type === 'image') {
        return function () {
          return doc.addImage(field.content, "JPEG", parseInt(field.x), parseInt(field.y), parseInt(field.w), parseInt(field.h));
        };
      } else if (field.type === 'shape') {
        var shape = getShapeById(field.content);
        if (shape && shape.svg) {
          return convertSvgToImage(shape.svg, field.w, field.h, field.styles.color.hex).then(function (svgAsImg) {
            if (svgAsImg) {
              return function () {
                return doc.addImage(svgAsImg, "PNG", parseInt(field.x), parseInt(field.y), parseInt(field.w), parseInt(field.h));
              };
            }
          })["catch"](function (error) {
            return console.error('Ошибка преобразования SVG в PNG:', error);
          });
        }
      } else if (field.type === 'qrcode') {
        var tempDiv = document.createElement('div');
        document.body.appendChild(tempDiv);
        var qrCode = new QRCode(tempDiv, {
          text: field.content,
          width: parseInt(field.w * 10),
          height: parseInt(field.h * 10),
          colorDark: "#000000",
          colorLight: "#ffffff",
          correctLevel: QRCode.CorrectLevel.H
        });
        var qrCanvas = tempDiv.querySelector('canvas');
        var qrImage = qrCanvas.toDataURL("image/jpeg");
        document.body.removeChild(tempDiv);
        return function () {
          return doc.addImage(qrImage, "JPEG", parseInt(field.x), parseInt(field.y), parseInt(field.w), parseInt(field.h));
        };
      } else {
        return function () {
          var textColor = hexToRGB(field.styles.color.hex);
          var fontStyle = 'normal';
          var x = parseInt(field.x);
          var fontSize = parseInt(field.styles.fontSize.replace('px', ''));
          var y = parseInt(field.y) + fontSize;
          var fieldWidth = parseInt(field.w) - 12;
          var options = {
            maxWidth: fieldWidth,
            align: field.styles.textAlign,
            lineHeightFactor: 1.25
          };
          if (field.styles.textAlign === 'right') {
            x += fieldWidth;
          } else if (field.styles.textAlign === 'center') {
            x += 6 + fieldWidth / 2;
          } else {
            x += 6;
          }
          if (field.styles.fontWeight && field.styles.fontWeight !== "false") {
            fontStyle = 'bold';
            if (field.styles.fontStyle && field.styles.fontStyle !== "false") {
              fontStyle = 'bolditalic';
            }
          } else if (field.styles.fontStyle && field.styles.fontStyle !== "false") {
            fontStyle = 'italic';
          }
          doc.setTextColor(field.styles.color.hex);
          doc.setFontSize(fontSize * 1.4);
          doc.setFont(field.styles.fontFamily, fontStyle);
          var lines = field.content.split('\n');
          lines.forEach(function (line, index) {
            doc.text(line, x, y + index * fontSize * options.lineHeightFactor, options);
          });
        };
      }
      return Promise.resolve(null);
    });
    Promise.all(imagePromises).then(function (drawFunctions) {
      drawFunctions.forEach(function (drawFunc) {
        if (typeof drawFunc === 'function') drawFunc();
      });
      if (preview) {
        generateCertificatePreview(doc);
        $('.masterstudy-page-certificate__download .masterstudy-button').attr('href', doc.output('bloburl'));
        $('.masterstudy-page-certificate__download .masterstudy-button').removeClass('masterstudy-button_loading');
      } else {
        var isSafari = /^((?!chrome|android).)*safari/i.test(navigator.userAgent);
        if (isSafari) {
          doc.autoPrint();
          doc.output('save', 'Certificate.pdf');
        } else {
          window.open(doc.output('bloburl'));
        }
      }
    });
  }
  function getShapeById(shapeId) {
    return course_certificate.shapes.find(function (shape) {
      return shape.id === shapeId;
    });
  }
  function convertSvgToImage(svgContent, containerWidth, containerHeight, newColor) {
    var scaleFactor = arguments.length > 4 && arguments[4] !== undefined ? arguments[4] : 2;
    return new Promise(function (resolve, reject) {
      var updatedSvgContent = svgContent.replace(/(<path[^>]*?)fill="[^"]*"/g, "$1fill=\"".concat(newColor, "\""));
      var svgBlob = new Blob([updatedSvgContent], {
        type: 'image/svg+xml'
      });
      var url = URL.createObjectURL(svgBlob);
      var img = new Image();
      img.onload = function () {
        var originalWidth = img.width;
        var originalHeight = img.height;
        var adjustedWidth = containerWidth * scaleFactor;
        var adjustedHeight = containerHeight * scaleFactor;
        var scalingFactor = Math.min(adjustedWidth / originalWidth, adjustedHeight / originalHeight);
        var newWidth = originalWidth * scalingFactor;
        var newHeight = originalHeight * scalingFactor;
        var canvas = document.createElement('canvas');
        canvas.width = adjustedWidth;
        canvas.height = adjustedHeight;
        var context = canvas.getContext('2d');
        context.clearRect(0, 0, adjustedWidth, adjustedHeight);
        var offsetX = (adjustedWidth - newWidth) / 2;
        var offsetY = (adjustedHeight - newHeight) / 2;
        context.drawImage(img, offsetX, offsetY, newWidth, newHeight);
        var base64Image = canvas.toDataURL('image/png', 1.0);
        resolve(base64Image);
      };
      img.onerror = function (error) {
        reject(new Error("Error converting SVG to PNG"));
      };
      img.src = url;
    });
  }
  function generateCertificatePreview(doc) {
    var pdfBlob = doc.output('blob');
    var pdfUrl = URL.createObjectURL(pdfBlob);
    var loadingTask = pdfjsLib.getDocument(pdfUrl);
    loadingTask.promise.then(function (pdf) {
      return pdf.getPage(1);
    }).then(function (page) {
      var viewport = page.getViewport({
        scale: 1.0
      });
      var canvas = document.createElement('canvas');
      var ctx = canvas.getContext('2d');
      canvas.height = viewport.height;
      canvas.width = viewport.width;
      var renderContext = {
        canvasContext: ctx,
        viewport: viewport
      };
      page.render(renderContext).promise.then(function () {
        canvas.toBlob(function (blob) {
          var imgUrl = URL.createObjectURL(blob);
          $('.masterstudy-page-certificate__preview').removeClass('masterstudy-page-certificate__preview_empty');
          $('.masterstudy-page-certificate__preview').html('<img src="' + imgUrl + '" alt="Certificate Preview">');
        }, 'image/jpeg');
      });
    });
  }
  function hexToRGB(h) {
    var r = 0,
      g = 0,
      b = 0;

    // 3 digits
    if (h.length == 4) {
      r = "0x" + h[1] + h[1];
      g = "0x" + h[2] + h[2];
      b = "0x" + h[3] + h[3];
    } else if (h.length == 7) {
      r = "0x" + h[1] + h[2];
      g = "0x" + h[3] + h[4];
      b = "0x" + h[5] + h[6];
    }
    return {
      r: parseInt(r),
      g: parseInt(g),
      b: parseInt(b)
    };
  }
})(jQuery);