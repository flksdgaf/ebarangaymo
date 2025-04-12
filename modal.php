<!-- View/Edit Modal -->
<div id="requestModal" class="modal" style="display: none;">
    <div class="modal-content">
        <span class="close" onclick="closeModal()">&times;</span>
        <h2 id="modalTitle">Request Info</h2>
        <div id="modalBody">
        <!-- Details will be inserted here -->
        </div>
    </div>
</div>

<!-- Add some CSS styles for the modal -->
<style>
.modal {
position: fixed;
top: 0;
left: 0;
width: 100%;
height: 100%;
z-index: 999;
background-color: rgba(0,0,0,0.5);
display: flex;
align-items: center;
justify-content: center;
}
.modal-content {
background-color: #fff;
padding: 30px;
width: 90%;
max-width: 700px;
border-radius: 15px;
font-family: 'Segoe UI', sans-serif;
box-shadow: 0 4px 20px rgba(0,0,0,0.2);
position: relative;
max-height: 90vh;
overflow-y: auto;
}
.modal .close {
position: absolute;
top: 15px;
right: 20px;
font-size: 24px;
cursor: pointer;
}

.modal h3 {
margin-top: 20px;
margin-bottom: 10px;
font-size: 1.1rem;
}

.modal p {
margin: 4px 0;
}
</style>
