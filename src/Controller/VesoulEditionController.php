<?php

namespace App\Controller;

use App\Entity\Book;
use App\Repository\BookRepository;

use App\Repository\CartRepository;
use App\Repository\GenraRepository;
use App\Repository\AuthorRepository;
use Symfony\Component\HttpFoundation\Request;
use Doctrine\Common\Persistence\ObjectManager;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Session\Attribute\AttributeBag;

use Symfony\Component\HttpFoundation\Session\Storage\NativeSessionStorage;
use Symfony\Component\HttpFoundation\Session\Attribute\NamespacedAttributeBag;

use Symfony\Component\HttpFoundation\JsonResponse;

class VesoulEditionController extends AbstractController
{

    /**
     * @var float
     */
    public $totalCost = 0.00;

    /**
     * @Route("/", name="home")
     */
    public function home(SessionInterface $session, BookRepository $repoBook, GenraRepository $repoGenra, AuthorRepository $repoAuthor)
    {
        // $session->remove('panier');

        // Si le panier est bien existant, capte le, et compte le nombre d'articles contenu.
        if($session->get('panier')) {

            $panier = $session->get('panier');

        } else { // Sinon crée le et initialise à 0 le nombre d'articles contenu.
            $session->set('panier', []);
        }
    
        $allBooks = $repoBook->findAllBooks();

        // Garder uniquement les 9 premiers livres de la BDD pour la page d'acceuil
        $books = [];
        for($i = 0; $i < 9; $i++) {
            array_push($books, $allBooks[$i]);
        }
        
        // $books = $repoBook->findBy(['year' => '2019']);

        // $booksImages = $books->getImage()->getUrl();
        $genras = $repoGenra->findAll();
        $authors = $repoAuthor->findAll();


        return $this->render('vesoul-edition/home.html.twig', [
            'books' => $books,
            'genras' => $genras,
            'authors' => $authors,
            
        ]);

    }

    /**
     * @Route("/home/load", name="load-home")
     */
    public function homeload(Request $request) {
        
        $data = ['foo1' => 'bar1', 'foo2' => 'bar2'];
        return new JsonResponse($data);    
       
    }

    /**
     * @Route("/panier/add/{id}", name="addItem")
     */
    public function addItem(Book $book, SessionInterface $session, ObjectManager $manager, BookRepository $repoBook)
    {
        $id = $book->getId();
        $title = $book->getTitle();
        $author = $book->getAuthor();
        $price = $book->getPrice();
        $stock = $book->getStock();
        $images = $book->getImages();
        $image = $images[0]->getUrl(); // Juste la couverture du livre.


        if ($stock > 0) {

            $book->setStock($stock - 1);
            $panier = $session->get('panier');
            
            $manager->persist($book);
            $manager->flush();
              
            if (array_key_exists($id, $panier)) {

                $panier[$id]['quantity']++;

            } else {
                
                $panier[$id] = [
                    'id' => $id,
                    'title'=> $title,
                    'firstname'=> $author->getFirstname(),
                    'lastname'=> $author->getLastname(),
                    'quantity'=> 1,
                    'price'=> $price,
                    'image' => $image               
                ];   
            }

            $session->set('panier', $panier);
            
            return $this->redirectToRoute('panier');
        } else {
            return $this->redirectToRoute('home');
        }
    }

    /**
     * @Route("/panier/reduce/{id}", name="reduceItem")
     */
    public function reduceItem(Book $book, SessionInterface $session, ObjectManager $manager)
    {   
        $stock = $book->getStock();
        $id = $book->getId();
        
        $panier = $session->get('panier');
        
        if (array_key_exists($id, $panier) && $panier[$id]['quantity'] > 1) {
            
            $panier[$id]['quantity']--;
            $book->setStock($stock + 1);
            $session->set('panier', $panier);
            $manager->persist($book);
            $manager->flush();

        } 

        return $this->redirectToRoute('panier');
    }

    /**
     * @Route("/panier/delete/{id}", name="deleteItem")
     */
    public function deleteItem(Book $book, SessionInterface $session, ObjectManager $manager)
    {
        $id = $book->getId();
        $stock = $book->getStock();
        $panier = $session->get('panier');
        
        $book->setStock($stock + $panier[$id]['quantity']);

        unset($panier[$id]);
        $session->set('panier', $panier);
        $manager->persist($book);
        $manager->flush();

        // dump($panier);
        // die();

        return $this->redirectToRoute('panier');
        
    }

    /**
     * @Route("/product/{id}", name="product")
     */
    public function showProduct($id, BookRepository $repo)
    {
        $book = $repo->findBook($id);

        return $this->render('vesoul-edition/product.html.twig', [
            'book' => $book
        ]);
    }

    /**
     * @Route("/panier", name="panier")
     */
    public function showPanier(SessionInterface $session)
    {

        $panier = $session->get('panier');
        foreach ($panier as $elem) {
            $this->totalCost += $elem['price'] * $elem['quantity'];                
        }

        return $this->render('vesoul-edition/panier.html.twig', [
            'total' => $this->totalCost
        ]);
    }

    /**
     * @Route("/commande", name="commander")
     */
    public function showCommande(SessionInterface $session)
    {
        return $this->render('vesoul-edition/commande.html.twig', [
            'controller_name' => 'FrontController',
        ]);
    }

    /**
     * @Route("/confirmation", name="commander")
     */
    public function showConfirmation()
    {
        return $this->render('vesoul-edition/confirmation.html.twig', [
            'controller_name' => 'FrontController',
        ]);
    }

   
}
