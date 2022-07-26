<?php
namespace Controllers;
 use Classes\Email;
 use Model\Usuario;
 use MVC\Router; 
 class LoginController{
    public static function login(Router $router){
        $alertas = [];

        $auth = new Usuario;
        if($_SERVER['REQUEST_METHOD'] === 'POST'){
           $auth = new Usuario($_POST);
          
           $alertas = $auth->validarLogin();
          
           if(empty($alertas)){
                //Comprobar que exista el usuario
               
                $usuario = Usuario::where('email',$auth->email);
              
               if($usuario){
                    //verificar el password
                   
                   if($usuario->comprobarPasswordAndVerificado($auth->password)){
                        //Autenticar el usuario
                        session_start();

                        $_SESSION['id'] = $usuario->id;///le paso el id del usuario a autenticar
                        $_SESSION['nombre'] = $usuario->nombre . " " . $usuario->apellido; //le pasamos el nombre del usuario a la sesison
                        $_SESSION['email'] = $usuario->email;
                        $_SESSION['login'] = true;

                        if($usuario->admin === "1"){
                           $_SESSION['admin'] = $usuario->admin ?? null;

                           header('Location: /admin');
                        }else{
                           header('Location: /cita');
                        };
                     
                   };
               }else{
                    Usuario::setAlerta('error','Usuario no encontrado');

               }
                
           }
        }

        $alertas = Usuario::getAlertas();


        $router->render('auth/login',[
            'alertas' => $alertas,
            'auth' => $auth
            
        ]);
    }
    public static function logout(){
        session_start();
        
        $_SESSION = [];
        header('Location: /');
    }
    public static function olvide(Router $router){

        $alertas = [];
        if($_SERVER['REQUEST_METHOD'] === 'POST'){
            $auth = new Usuario($_POST);
            $alertas = $auth->validarEmail();

            if(empty($alertas)){
                $usuario = Usuario::where('email',$auth->email);

                if($usuario && $usuario->confirmado === "1"){
                   
                    //Generar un token
                    $usuario->crearToken();
                    $usuario->guardar();

                    // Enviar email
                    $email = new Email($usuario->email, $usuario->nombre,$usuario->token);
                    $email->enviarInstrucciones();


                    //Alerta de exito
                    Usuario::setAlerta('exito','Mensaje enviado,Revisa tu email');
                   

                }else{
                    Usuario::setAlerta('error', 'El Usuario no existe o no esta confirmado');
                
                }
            }
          
        }
        $alertas = Usuario::getAlertas();
       $router->render('auth/olvide-password', [
            'alertas' => $alertas
       ]);
    } 
    public static function recuperar(Router $router){
       
       $alertas = [];
       $error  = false;
       $token = s($_GET['token']);


       //Buscar usuario por su token
       $usuario = Usuario::where('token',$token);

        if(empty($usuario)){
            Usuario::setAlerta('error','Token No Válido');
            $error = true;
        }

        if($_SERVER['REQUEST_METHOD'] === 'POST'){
            //Leer el nuevo Password y guardarlo

            $password = new Usuario($_POST);

            $alertas = $password->validarPassword();

            if(empty($alertas)){
                $usuario->password = null;
                
                $usuario->password = $password->password;
                $usuario->hashPassword();
                $usuario->token = null;

                $resultado = $usuario->guardar();
                if($resultado){
                    header('Location: /');
                }
                debuguear($usuario);
            }
        }
       

        $alertas = Usuario::getAlertas();
        $router->render('auth/recuperar-password',[
            'alertas' => $alertas,
            'error' => $error
       ]);
    }
    public static function crear(Router $router){
        $usuario = new Usuario;
        
        //Alertas vacias 
       $alertas = [];
        if($_SERVER['REQUEST_METHOD'] === 'POST') {
            
            $usuario->sincronizar($_POST);
            $alertas = $usuario->validarNuevaCuenta();

            //Revisar que alerta este vacio
            if(empty($alertas)){
                //Verificar que el usuario no este registrado
                $resultado = $usuario->existeUsuario();

                if($resultado->num_rows){
                    $alertas =  Usuario::getAlertas();
                }else{
                    //Hasehear el password
                    $usuario->hashPassword();
                    

                        //Generar un Token unico
                     $usuario->crearToken();
                        //Enviar el email
                       $email = new Email($usuario->nombre,$usuario->email, $usuario->token);

                        $email->enviarConfirmacion();

                    //Crear el usuario
                   
                    $resultado = $usuario->guardar();
                   
                    if($resultado){
                        header('Location: /mensaje');
                    }
                   
                }
            }
          
           
        }
        $router->render('auth/crear-cuenta',[
            'usuario' => $usuario,
            'alertas'=>$alertas

        ]);
    }
    public static function mensaje(Router $router){

        $router->render('auth/mensaje');
    }

    public static function confirmar(Router $router){
        $alertas = [];

        $token = s($_GET['token']);
       $usuario = Usuario::where('token',$token);

       if(empty($usuario)){
            //Mostrar mensaje de error
           Usuario::setAlerta('error','Token No Válido');//envia una alerta al usuario
       }else{
        //MOdificar a usuario confirmado
        $usuario->confirmado = "1";//cambia el confirmado en la db para confirmarlo
        $usuario-> token = null;//elimina el token enviado
        $usuario->guardar();//guarda los cambios
        Usuario::setAlerta('exito','Cuenta Comprobada Correctamente');//envia una alerta de todo bien
       }


       //Obtener alertas
       $alertas = Usuario::getAlertas();

       //Renderizar la vista
        $router->render('auth/confirmar-cuenta',[
            'alertas' => $alertas
        ]);
    }
 }  