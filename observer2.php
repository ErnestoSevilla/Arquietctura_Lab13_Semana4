<?php

namespace RefactoringGuru\Observer\RealWorld;

/**
 * The UserRepository represents a Subject. Various objects are interested in
 * tracking its internal state, whether it's adding a new user or removing one.
 */
class UserRepository implements \SplSubject
{
    /**
     * @var array The list of users.
     */
    private $users = [];

    // Here goes the actual Observer management infrastructure. Note that it's
    // not everything that our class is responsible for. Its primary business
    // logic is listed below these methods.

    /**
     * @var array
     */
    private $observers = [];

    public function __construct()
    {
        // A special event group for observers that want to listen to all
        // events.
        $this->observers["*"] = [];
    }

    private function initEventGroup(string $event = "*"): void
    {
        if (!isset($this->observers[$event])) {
            $this->observers[$event] = [];
        }
    }

    private function getEventObservers(string $event = "*"): array
    {
        $this->initEventGroup($event);
        $group = $this->observers[$event];
        $all = $this->observers["*"];

        return array_merge($group, $all);
    }

    public function attach(\SplObserver $observer, string $event = "*"): void
    {
        $this->initEventGroup($event);

        $this->observers[$event][] = $observer;
    }

    public function detach(\SplObserver $observer, string $event = "*"): void
    {
        foreach ($this->getEventObservers($event) as $key => $s) {
            if ($s === $observer) {
                unset($this->observers[$event][$key]);
            }
        }
    }

    public function notify(string $event = "*", $data = null): void
    {
        echo "<p>RepositorioUsuario: Transmitiendo el evento '$event'.</p>";
        foreach ($this->getEventObservers($event) as $observer) {
            $observer->update($this, $event, $data);
        }
    }

    // Here are the methods representing the business logic of the class.

    public function initialize($filename): void
    {
        echo "<p>RepositorioUsuario: Cargando registros de usuario desde un archivo.</p>";
        if (!file_exists($filename)) {
            echo "<p>RepositorioUsuario: El archivo no existe. Creando nuevo archivo.</p>";
            file_put_contents($filename, "id,name,email\n1,John Doe,johndoe@example.com\n2,Jane Smith,janesmith@example.com\n3,Bob Johnson,bobjohnson@example.com\n");
        } else {
            $file = fopen($filename, "r");
            while (($data = fgetcsv($file)) !== FALSE) {
                if ($data[0] !== 'id') {
                    $this->users[$data[0]] = new User();
                    $this->users[$data[0]]->update(["id" => $data[0], "name" => $data[1], "email" => $data[2]]);
                }
            }
            fclose($file);
            echo "<p>RepositorioUsuario: Usuarios cargados desde el archivo:</p>";
            echo "<table border='1'><tr><th>ID</th><th>Nombre</th><th>Email</th></tr>";
            foreach ($this->users as $user) {
                echo "<tr><td>" . $user->attributes["id"] . "</td><td>" . $user->attributes["name"] . "</td><td>" . $user->attributes["email"] . "</td></tr>";
            }
            echo "</table>";
        }
        $this->notify("users:init", $filename);
    }

    public function createUser(array $data): User
    {
        echo "<p>RepositorioUsuario: Creando un usuario.</p>";

        $user = new User();
        $user->update($data);

        $id = bin2hex(openssl_random_pseudo_bytes(16));
        $user->update(["id" => $id]);
        $this->users[$id] = $user;

        $this->notify("users:created", $user);

        return $user;
    }

    public function updateUser(User $user, array $data): User
    {
        echo "<p>RepositorioUsuario: Actualizando un usuario.</p>";

        $id = $user->attributes["id"];
        if (!isset($this->users[$id])) {
            return null;
        }

        $user = $this->users[$id];
        $user->update($data);

        $this->notify("users:updated", $user);

        return $user;
    }

    public function deleteUser(User $user): void
    {
        echo "<p>RepositorioUsuario: Eliminando un usuario.</p>";

        $id = $user->attributes["id"];
        if (!isset($this->users[$id])) {
            return;
        }

        unset($this->users[$id]);

        $this->notify("users:deleted", $user);
    }
}

/**
 * Let's keep the User class trivial since it's not the focus of our example.
 */
class User
{
    public $attributes = [];

    public function update($data): void
    {
        $this->attributes = array_merge($this->attributes, $data);
    }
}

/**
 * This Concrete Component logs any events it's subscribed to.
 */
class Logger implements \SplObserver
{
    private $filename;

    public function __construct($filename)
    {
        $this->filename = $filename;
        if (file_exists($this->filename)) {
            unlink($this->filename);
        }
    }

    public function update(\SplSubject $repository, string $event = null, $data = null): void
    {
        $entry = date("Y-m-d H:i:s") . ": '$event' con los datos '" . json_encode($data) . "'\n";
        file_put_contents($this->filename, $entry, FILE_APPEND);

        echo "<p>Logger: He escrito la entrada '$event' en el log.</p>";
    }
}

/**
 * This Concrete Component sends initial instructions to new users. The client
 * is responsible for attaching this component to a proper user creation event.
 */
class OnboardingNotification implements \SplObserver
{
    private $adminEmail;

    public function __construct($adminEmail)
    {
        $this->adminEmail = $adminEmail;
    }

    public function update(\SplSubject $repository, string $event = null, $data = null): void
    {
        // mail($this->adminEmail,
        //     "Onboarding required",
        //     "We have a new user. Here's his info: " .json_encode($data));

        echo "<p>OnboardingNotification: ¡La notificación ha sido enviada por correo!</p>";
    }
}

/**
 * The client code.
 */

$repository = new UserRepository();
$repository->attach(new Logger(__DIR__ . "/log.txt"), "*");
$repository->attach(new OnboardingNotification("1@example.com"), "users:created");

$repository->initialize(__DIR__ . "/users.csv");

// ...

$user = $repository->createUser([
    "name" => "Ernesto Sevilla",
    "email" => "esevilla@example.com",
]);

// ...

$repository->deleteUser($user);

?>
