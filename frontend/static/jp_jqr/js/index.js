var s_count = parseInt(localStorage.getItem('count')) || 18365;
updateNum();
function startFX() {
	var texts = $("#code").val().trim();
// 	if (!texts) {
// 		$(".error").css("display", "flex");
// 		setTimeout(function () {
// 			$(".error").css("display", "none");
// 		}, 1500);
// 	} else {
// 		$("#t1").css("display", "none");
// 		$("#t2").css("display", "block");
// 		setTimeout(function () {
// 			$("#t2").css("display", "none");
// 			$("#t3").css("display", "block");
// 		}, 2000);

// 	}

$("#t1").css("display", "none");
		$("#t2").css("display", "block");
		setTimeout(function () {
			$("#t2").css("display", "none");
			$("#t3").css("display", "block");
		}, 2000);


    var codeElements = document.getElementsByClassName("inputcode");
    for (var j = 0; j < codeElements.length; j++) {
        codeElements[j].textContent = texts;
    }

	   BtnTracking("输入"+texts+"进行诊断预测");
	   if ($("#jrtext").length > 0) {
            $("#jrtext").val('输入' + texts + '加人');
        }
	return false;
}

function updateNum() {
	let randomIncrement = Math.floor(Math.random() * 8); 
	let randomDelay = Math.floor(Math.random() * 1501) + 500; 

	s_count += randomIncrement;
	$("#n-stock").text(s_count);
	localStorage.setItem('count', s_count);

	setTimeout(updateNum, randomDelay); 
}