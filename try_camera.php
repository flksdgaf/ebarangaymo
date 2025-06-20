<!-- index.php -->
<video id="preview" autoplay playsinline width="320" height="240"></video>
<button id="snap">Take Photo</button>
<canvas id="canvas" width="320" height="240" style="display:none"></canvas>

<script>
const video = document.getElementById('preview');
const canvas = document.getElementById('canvas');
const snapBtn = document.getElementById('snap');

// 1. Ask for camera
navigator.mediaDevices.getUserMedia({ video: true })
  .then(stream => video.srcObject = stream)
  .catch(err => alert('Camera error: ' + err));

// 2. On button click, draw frame to canvas & upload
snapBtn.addEventListener('click', () => {
  const ctx = canvas.getContext('2d');
  ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

  // Convert canvas to Blob
  canvas.toBlob(blob => {
    const form = new FormData();
    form.append('photo', blob, 'snapshot.jpg');

    fetch('upload.php', { method: 'POST', body: form })
      .then(res => res.json())
      .then(json => {
        if (json.success) {
          alert('Saved as '+json.filename);
        } else {
          alert('Upload failed');
        }
      })
      .catch(console.error);
  }, 'image/jpeg');
});
</script>
