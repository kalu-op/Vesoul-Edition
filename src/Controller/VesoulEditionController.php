<?php

namespace App\Controller;

use App\Entity\Book;
use App\Repository\BookRepository;

use App\Repository\CartRepository;
use App\Repository\GenraRepository;
use App\Repository\AuthorRepository;
use Symfony\Component\HttpFoundation\Request;
use Doctrine\Common\Persistence\ObjectManager;

use Symfony\Component\Routing\Annotation\Route;

use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Session\Attribute\AttributeBag;

use Symfony\Component\HttpFoundation\Session\Storage\NativeSessionStorage;
use Symfony\Component\HttpFoundation\Session\Attribute\NamespacedAttributeBag;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Doctrine\ORM\Query\Expr\GroupBy;
class VesoulEditionController extends AbstractController
{

    /**
     * @var float
     */
    public $totalCost = 0.00;

    /**
     * @Route("/", name="home")
     */
    public function home(Request $request, SessionInterface $session, BookRepository $repoBook, GenraRepository $repoGenra, AuthorRepository $repoAuthor)
    {
        // $session->remove('panier');

        if($session->get('panier')) {

            $panier = $session->get('panier');

        } else { 
            $session->set('panier', []);
        }
    
        $allBooks = $repoBook->findAllBooksByAscName();
        
        $genras = $repoGenra->findAll();
        $authors = $repoAuthor->findAll();
        $maxAndMinYear = $repoBook->maxAndMinYear();
        $minYear = $maxAndMinYear[0]['minyear'];
        $maxYear = $maxAndMinYear[0]['maxyear'];
        
        
        return $this->render('vesoul-edition/home.html.twig', [
            'genras' => $genras,
            'authors' => $authors,
            'minyear' => $minYear,
            'maxyear' => $maxYear

        ]);
    }   
   

     /**
     * @Route("/home/search/bytitle/{searchValue}", name="search-bytitle")
     */
    public function searchByTitle(Request $request, SessionInterface $session, BookRepository $repoBook,  GenraRepository $repoGenra, AuthorRepository $repoAuthor, string $searchValue) {
        
        $books = [];

        if( strlen( $searchValue ) > 0 ){
            $books = $repoBook->searchByTitle($searchValue);
        }
        
        if($session->get('panier')) {

            $panier = $session->get('panier');

        } else { 
            $session->set('panier', []);
        }

        $genras = $repoGenra->findAll();
        $authors = $repoAuthor->findAll();
        $maxAndMinYear = $repoBook->maxAndMinYear();
        $minYear = $maxAndMinYear[0]['minyear'];
        $maxYear = $maxAndMinYear[0]['maxyear'];
        
        return $this->render('vesoul-edition/home.html.twig', [
            'genras' => $genras,
            'authors' => $authors,
            'minyear' => $minYear,
            'maxyear' => $maxYear,
            'books'   => $books,
            'searchValue' => $searchValue

        ]);
        

    }

     /**
     * @Route("/home/search/ajax/{searchValue}", name="search-autocomplete")
     */
    public function autocomplete(Request $request, BookRepository $repoBook, string $searchValue) {
        
        $books = [];
        
        if( strlen( $searchValue ) >= 3 ){
            $books = $repoBook->findTitle($searchValue);
        }
        
        $response = new Response();
        if( count($books) > 0 ){
            
            $response->setContent(json_encode([
                'books' => $books,
            ]));
            $response->setStatusCode(Response::HTTP_OK);
            $response->headers->set('Content-Type', 'application/json');
            
        }else{
            
            $response->headers->set('Content-Type', 'text/plain');
            $response->setStatusCode(Response::HTTP_NO_CONTENT);

        }
        

        return $response;
    }

    /**
     * @Route("/home/load", name="load-home")
     */
    public function homeload(Request $request, BookRepository $repoBook) {
        
        $page = $request->get('page');
        $orderBy = $request->get('orderBy');
        $new = $request->get('new');
        $genre = strlen($request->get('genre')) > 0 ? explode(',', $request->get('genre')) : []; 
        $author = strlen($request->get('author')) > 0 ? explode(',', $request->get('author')) : [];
        $yearmin = $request->get('yearmin');
        $yearmax = $request->get('yearmax');
        $title = $request->get('title');

        $max_per_page = 9;

        $total_books = $repoBook->countBooks($new, $genre, $author, $yearmin, $yearmax, $title);
        $pages = ceil($total_books / $max_per_page);

        
        
        $offset = ($page - 1) * $max_per_page;

        // return new JsonResponse($repoBook->findPageOfListBook($offset));

        $books = $repoBook->findPageOfListBook($offset, $orderBy, $new, $genre, $author, $yearmin, $yearmax, $title);
        $response = new Response();
        // $response->setContent( $this->render('ajax/page-book.html.twig', 
        //         [
        //             'books' => $books
        //         ]
        //     )
        // );
        $response->setCharset('utf-8');
        $response->headers->set('Content-Type', 'text/html');
        $response->headers->set('X-TotalBooks', $total_books );
        $response->headers->set('X-TotalPage', $pages );
        $response->setStatusCode(Response::HTTP_OK);
        $response->send();
        return $this->render(
            'ajax/page-book.html.twig', 
            [
                'books' => $books
            ]
        );
        
        
    }


    /**
    * @Route("/ascName", name="sortByAscName")
    *
    * @param \App\Repository\BookRepository
    */
    public function sortByAscName(BookRepository $repo) : JsonResponse
    {
        $books = $repo->findAllBooksByAscName();
        $arrayBooks = [];
        $data = [];
        $i = 0;

        foreach($books as $key => $book){
            $i++;
            $arrayBooks[$key + 1] = $this->render('ajax/book.html.twig', ['book' => $book]);
            $data[] = $arrayBooks[$i]->getContent();
        }

        $json = new JsonResponse($data, 200);

        return $json;
    }

    /**
     * 
     * 
    * @Route("/descName", name="sortByDescName")
    */
    public function sortByDescName(BookRepository $repo) : JsonResponse
    {
        $books = $repo->findAllBooksByDescName();
        $arrayBooks = [];
        $data = [];
        $i = 0;

        foreach($books as $key => $book){
            $i++;
            $arrayBooks[$key + 1] = $this->render('ajax/book.html.twig', ['book' => $book]);
            $data[] = $arrayBooks[$i]->getContent();
        }

        $json = new JsonResponse($data, 200);

        return $json;
       
    }

    /**
    * @Route("/ascYear", name="sortByAscYear")
    */
    public function sortByAscYear(BookRepository $repo) : JsonResponse
    {
        $books = $repo->findAllBooksByAscYear();
        $arrayBooks = [];
        $data = [];
        $i = 0;

        foreach($books as $key => $book){
            $i++;
            $arrayBooks[$key + 1] = $this->render('ajax/book.html.twig', ['book' => $book]);
            $data[] = $arrayBooks[$i]->getContent();
        }

        $json = new JsonResponse($data, 200);

        return $json;
    }

    /**
    * @Route("/descYear", name="sortByDescYear")
    */
    public function sortByDescYear(BookRepository $repo) : JsonResponse
    {
        $books = $repo->findAllBooksByDescYear();
        $arrayBooks = [];
        $data = [];
        $i = 0;

        foreach($books as $key => $book){
            $i++;
            $arrayBooks[$key + 1] = $this->render('ajax/book.html.twig', ['book' => $book]);
            $data[] = $arrayBooks[$i]->getContent();
        }

        $json = new JsonResponse($data, 200);

        return $json;
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
