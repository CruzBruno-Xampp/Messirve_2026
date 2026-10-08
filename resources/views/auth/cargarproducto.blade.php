<!DOCTYPE html>
<html lang="es">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Agregar Producto | MES.SIRVE</title>

    <link rel="stylesheet" href="{{ asset('css/cargarproducto.css') }}">

</head>

<body>

<div class="contenedor">

    <!-- ========================= -->
    <!-- SIDEBAR -->
    <!-- ========================= -->

    <aside class="sidebar">

        <div class="logo">

            <img src="{{ asset('images/logomessirve.png') }}" alt="Logo">
             <h3>MES.<span>SIRVE</span></h3>
            <h6>PINTRURERIA</h6>

        </div>

        <nav>

            <ul>

                <li>

                    <a href="#">

                        <img src="{{ asset('images/deashboard-w.png') }}">

                        Dashboard

                    </a>

                </li>

                <li class="activo">

                    <a href="#">

                        <img src="{{ asset('images/productos-w.png') }}">

                        Productos

                    </a>

                </li>

                <li>

                    <a href="#">

                        <img src="{{ asset('images/stock-w.png') }}">

                        Stock

                    </a>

                </li>

                <li>

                    <a href="#">

                        <img src="{{ asset('images/ventas-w.png') }}">

                        Ventas

                    </a>

                </li>

                <li>

                    <a href="#">

                        <img src="{{ asset('images/clientes-w.png') }}">

                        Clientes

                    </a>

                </li>

                <li>

                    <a href="#">

                        <img src="{{ asset('images/proveedores-w.png') }}">

                        Proveedores

                    </a>

                </li>

                <li>

                    <a href="#">

                        <img src="{{ asset('images/reportes-w.png') }}">

                        Reportes

                    </a>

                </li>

                <li>

                    <a href="#">

                        <img src="{{ asset('images/roles-w.png') }}">

                        Roles

                    </a>

                </li>

            </ul>

        </nav>

    </aside>


    <!-- ========================= -->
    <!-- CONTENIDO -->
    <!-- ========================= -->

    <main class="contenido">


        <!-- HEADER -->

        <header class="header">

            <div class="titulo">

                <img src="{{ asset('images/cuadra.png') }}">

                <h1>Agregar Producto</h1>

            </div>

            <div class="usuario">

                <img src="{{ asset('images/usuario1.png') }}">

                <span>Administrador</span>

            </div>

        </header>



        <!-- TARJETA -->
      
        <section class="card">

            <div class="botones-SYC">
                    <button
                            type="button"
                            class="subir-excel">

                                Subir Excel de productos

                    </button>

                <button
                            type="button"
                            class="subir-pdf">

                               Convertir archivo

                </button>
            </div>
                  
           
            <h2>Datos del producto</h2>
         

                <div class="formulario">


                    <!-- COLUMNA IZQUIERDA -->

                    <div class="izquierda">

                        <div class="grupo">

                            <label>Código de Barra</label>

                            <input
                            type="text"
                            placeholder="Ingrese código de barra">

                        </div>

                        <div class="grupo">

                            <label>Código Tersuave</label>

                            <input
                            type="text"
                            placeholder="Ingrese código Tersuave">

                        </div>

                        <div class="grupo">

                            <label>Descripción</label>

                            <textarea
                            placeholder="Ingrese descripción del producto"></textarea>

                        </div>

                        <div class="grupo">

                            <label>Marca</label>

                            <select>

                                <option>Seleccionar marca</option>

                            </select>

                        </div>

                        <div class="grupo">

                            <label>Categoría</label>

                            <select>

                                <option>Seleccionar categoría</option>

                            </select>

                        </div>

                        <div class="grupo">

                            <label>Proveedor</label>

                            <select>

                                <option>Seleccionar proveedor</option>

                            </select>

                        </div>

                    </div>




                    <!-- COLUMNA CENTRO -->

                    <div class="centro">


                        <div class="grupo">

                            <label>Línea</label>

                            <select>

                                <option>Seleccionar línea</option>

                            </select>

                        </div>

                        <div class="grupo">

                            <label>Grupo</label>

                            <select>

                                <option>Seleccionar grupo</option>

                            </select>

                        </div>

                        <div class="grupo">

                            <label>Subgrupo</label>

                            <select>

                                <option>Seleccionar subgrupo</option>

                            </select>

                        </div>

                        <div class="grupo">

                            <label>Precio costo</label>

                            <input
                            type="number"
                            placeholder="0.00">

                        </div>

                        <div class="grupo">

                            <label>Precio sin IVA</label>

                            <input
                            type="number"
                            placeholder="0.00">

                        </div>

                        <div class="grupo">

                            <label>Precio con IVA</label>

                            <input
                            type="number"
                            placeholder="0.00">

                        </div>

                    </div>




                    <!-- COLUMNA DERECHA -->

                    <div class="derecha">

                        
                       

 <label>Imagen del Producto</label>

<label for="archivo" id="contenedorImagen">

    <span id="mensajeImagen">Seleccione una imagen</span>

    <img id="preview" src="" alt="">

</label>

<input type="file" id="archivo" accept="image/*" hidden>

<p id="texto">Ningún archivo seleccionado</p>
                        

                   



                    </div>


                </div>



                <!-- SEGUNDA FILA -->

                <div class="fila2">

                    <div class="grupo">

                        <label>PVP</label>

                        <input
                        type="number"
                        placeholder="0.00">

                    </div>

                    <div class="grupo">

                        <label>Descuento Proveedor (%)</label>

                        <input
                        type="number"
                        placeholder="0">

                    </div>

                    <div class="grupo">

                        <label>Rentabilidad (%)</label>

                        <input
                        type="number"
                        placeholder="0">

                    </div>

                </div>




                <!-- ESTADO -->

                <div class="estado">

                    <label>Estado</label>

                    <div class="radio">

                        <label>

                            <input
                            type="radio"
                            name="estado"
                            checked>

                            Activo

                        </label>

                        <label>

                            <input
                            type="radio"
                            name="estado">

                            Inactivo

                        </label>

                    </div>

                </div>




                <!-- BOTONES -->

                <div class="botones">

                    <button
                    class="guardar">

                        Guardar

                    </button>

                    <button
                    type="reset"
                    class="limpiar">

                        Limpiar

                    </button>

                    <button
                    type="button"
                    class="cancelar">

                        Cancelar

                    </button>

                </div>


        </section>

    </main>

</div>
        <script>
    const input = document.getElementById("archivo");
const preview = document.getElementById("preview");
const texto = document.getElementById("texto");
const mensajeImagen = document.getElementById("mensajeImagen");

input.addEventListener("change", function () {

    if (this.files.length > 0) {

        texto.textContent = this.files[0].name;

        const lector = new FileReader();

        lector.onload = function (e) {

            preview.src = e.target.result;
            preview.style.display = "block";

            mensajeImagen.style.display = "none";
        }

        lector.readAsDataURL(this.files[0]);

    } else {

        texto.textContent = "Ningún archivo seleccionado";

        preview.style.display = "none";

        mensajeImagen.style.display = "block";
    }

});
        </script>
            

</body>

</html>