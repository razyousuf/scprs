<?phpinclude('db_connect.php');if(session_status() == PHP_SESSION_NONE) {    session_start();}$share_id = $_GET['share_id'] ?? null;if (!$share_id) {    die("No share ID provided.");}// Fetch paper details using share_id$stmt = $conn->prepare("SELECT p.paper_id, p.paperurl FROM papers p JOIN paper_shares ps ON p.paper_id = ps.paper_id WHERE ps.share_id = ?");$stmt->bind_param("i", $share_id);$stmt->execute();$result = $stmt->get_result();$paper = $result->fetch_assoc();if (!$paper) {    die("Paper not found.");}$pdfUrl = htmlspecialchars($paper['paperurl']);?><!DOCTYPE html><html lang="en"><head>    <meta charset="UTF-8">    <title>Review Paper</title>    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css">	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.13.216/pdf.min.js"></script>    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.13.216/pdf.worker.min.js"></script>		<style>		.pdf-page-container {             display: flex;             align-items: flex-start;             margin-bottom: 20px;         }        #pdf-render-container {             width: 100%;        }        .annotations-container {             width: 24%;             padding-left: 0px;         }        canvas {             width: 100%;            height: auto;        }        .annotation-input textarea {             width: 120%;            height: 80px;        }		.annotations-list {			margin-top: 5px;		}				.annotations-display {        width: calc(130%); /* Adjust the width as needed */        /* Other styles for the annotations display */		}			.annotation-item {			position: relative; /* Position relative for absolute positioning */		}		.like-icon {			position: absolute;			bottom: -10px;			right: 25px; /* Adjust the positioning of the like icon */			margin-left: 5px;			color: blue;			font-size: 20px;		}		.reply-icon {			position: absolute;			bottom: -10px;			right: 0; /* Position the reply icon at the far right */			margin-left: 4px;			color: green;			font-size: 20px;		}		.like-icon:hover,		.reply-icon:hover {			color: red; /* Change color on hover */		}		.like-icon i,		.reply-icon i {			cursor: pointer; /* Change cursor to pointer */		}        /* Media Query for responsiveness */        @media (max-width: 768px) {            .pdf-page-container {                flex-direction: column;            }            #pdf-render-container, .annotations-container {                width: 100%;            }        }	</style></head><body>    <div id="pdf-render-container"></div>    <script>    pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.13.216/pdf.worker.min.js';    var url = '<?= $pdfUrl ?>';    var container = document.getElementById('pdf-render-container');    var loadingTask = pdfjsLib.getDocument(url);    loadingTask.promise.then(function(pdf) {        for (let pageNum = 1; pageNum <= pdf.numPages; pageNum++) {            let pageContainer = document.createElement('div');            pageContainer.className = 'pdf-page-container';            let canvasContainer = document.createElement('div');            canvasContainer.className = 'canvas-container';            let canvas = document.createElement('canvas');            canvasContainer.appendChild(canvas);            pageContainer.appendChild(canvasContainer);            let annotationContainer = document.createElement('div');            annotationContainer.className = 'annotations-container';            // Annotation input area            let annotationInput = document.createElement('div');            annotationInput.className = 'annotation-input';            annotationInput.innerHTML = `                <textarea placeholder="Comment for page ${pageNum}..."></textarea>                <button onclick="submitAnnotation(this, ${pageNum})">Submit</button>					<!-- This container will hold the fetched annotations -->					<div class="annotations-display" data-page="${pageNum}"></div> `;            annotationContainer.appendChild(annotationInput);            // Area to display stored annotations            let annotationsDisplay = document.createElement('div');            annotationsDisplay.className = 'annotations-display';            annotationsDisplay.setAttribute('data-page', pageNum);            annotationContainer.appendChild(annotationsDisplay);            pageContainer.appendChild(annotationContainer);            container.appendChild(pageContainer);            pdf.getPage(pageNum).then(function(page) {                var context = canvas.getContext('2d');                var scale = 1.5;                var viewport = page.getViewport({ scale: scale });                canvas.height = viewport.height;                canvas.width = viewport.width;                var renderContext = {                    canvasContext: context,                    viewport: viewport                };                page.render(renderContext).promise.then(() => {                    loadAnnotations(pageNum);  // Load annotations after the page is rendered                });            });        }    });	function submitAnnotation(button, pageNum) {        var textarea = button.previousElementSibling; // Assuming the textarea is directly preceding the button        var content = textarea.value.trim(); // Getting the trimmed value of the textarea        if (content === "") {            alert("Annotation cannot be empty."); // Alert if the textarea is empty            return;        }				// Confirmation popup before submitting        var confirmSubmission = window.confirm("Are you sure you want to submit this annotation?");        if (!confirmSubmission) {            return; // Do nothing if the user cancels the submission        }        // AJAX request to save the annotation        $.ajax({        type: 'POST',        url: 'ajax.php',        data: {            action: 'save_annotation',            paper_id: <?= $paper['paper_id']; ?>,            reviewer_id: <?= $_SESSION['login_id']; ?>, // Assuming session holds the reviewer ID            share_id: <?= $share_id; ?>,            content: content,            page: pageNum,            type: 0 // Assuming 0 is the type for new annotations        },        success: function(response) {            try {                var jsonResponse = JSON.parse(response);                if (jsonResponse.success) {                    console.log("Annotation saved with ID:", jsonResponse.id);                    textarea.value = ""; // Clear the textarea after successful submission                    loadAnnotations(pageNum); // Reload annotations to display the new one                } else {                    alert("Failed to save annotation: " + jsonResponse.message);                }            } catch(e) {                console.error("Response was not valid JSON:", response);            }        },        error: function(xhr, status, error) {            alert("Error in saving annotation: " + xhr.responseText);        }    });}			function loadAnnotations(pageNum) {    $.ajax({        type: 'POST',        url: 'ajax.php',        data: {            action: 'fetch_annotations',            paper_id: <?= $paper['paper_id']; ?>,            share_id: <?= $share_id; ?>,            page: pageNum        },        dataType: 'json',        success: function(response) {            const annotationsDisplay = document.querySelector(`.annotations-display[data-page="${pageNum}"]`);            annotationsDisplay.innerHTML = ''; // Clear existing annotations            if (response.success && Array.isArray(response.annotations)) {                response.annotations.forEach(function(annotation) {                    const likeCount = response.likeCounts[annotation.annotation_id] || 0;                    const annotationItem = document.createElement('div');                    annotationItem.className = 'annotation-item';                    annotationItem.innerHTML = `                        <p>${annotation.firstname}: ${annotation.content}</p>                        <span class="like-icon" onclick="submitLike(${annotation.annotation_id}, 0)"><i class="fas fa-thumbs-up"></i> (${likeCount})</span>                        <span class="reply-icon" onclick="openReplyForm(${annotation.annotation_id})"><i class="fas fa-reply"></i></span>                    `;                    annotationsDisplay.appendChild(annotationItem);                });            } else {                console.error('Expected an array of annotations, received:', response);                annotationsDisplay.textContent = 'No annotations found or error in data.';            }        },        error: function(xhr, status, error) {            console.error("Failed to load annotations:", xhr.responseText);            const annotationsDisplay = document.querySelector(`.annotations-display[data-page="${pageNum}"]`);            annotationsDisplay.textContent = 'Failed to load annotations.';        }    });}		function submitLike(annotationId, replyId) {    $.ajax({        url: 'ajax.php',        method: 'POST',        data: {            action: 'save_like',            comment_id: annotationId,            reply_id: replyId  // This will be 0 if it's not a reply        },        dataType: 'json', // Ensure that the response is treated as JSON        success: function(response) {            if (response.success) {                //alert('Like added!');                // Update UI here if needed				setTimeout(function(){						location.reload();					},80)            } else {                alert('Failed to add like: ' + response.message);            }        },        error: function(xhr, status, error) {            alert("Error in saving like: " + xhr.responseText);  // Logs the response text that led to the error        }    });}function submitLike(annotationId) {    $.ajax({        url: 'ajax.php',        method: 'POST',        data: {            action: 'save_like',            comment_id: annotationId        },        dataType: 'json',        success: function(response) {            if (response.success) {                const likeIcon = document.querySelector(`span.like-icon[data-comment-id='${annotationId}']`);                if (likeIcon) {                    likeIcon.innerHTML = `<i class="fas fa-thumbs-up"></i> ${response.likeCount}`;                }				setTimeout(function(){					location.reload();				},0)            } else {                console.log(response.message); // Log the message for debug            }        },        error: function(xhr) {            console.error("Error in saving like: " + xhr.responseText);        }    });}function openReplyForm(commentId) {    var replyText = prompt("Enter your reply:");    if (replyText) {        $.ajax({            url: 'ajax.php',            method: 'POST',            data: {                action: 'save_reply',                comment_id: commentId,                reply: replyText            },            success: function(response) {                if (response.success) {                    alert('Reply added!');                } else {                    alert('Failed to add reply: ' + response.message);                }            }        });    }}</script></body></html>