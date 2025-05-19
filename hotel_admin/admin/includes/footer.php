<?php
// hotel_admin/admin/includes/footer.php
?>
            </div> </div> </div> <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const menuToggle = document.getElementById("menu-toggle");
            if (menuToggle) {
                menuToggle.addEventListener("click", function(e) {
                    e.preventDefault();
                    const wrapper = document.getElementById("wrapper");
                    if (wrapper) {
                        wrapper.classList.toggle("toggled");
                    }
                });
            }
        });
    </script>

    </body>
</html>