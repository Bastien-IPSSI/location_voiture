<?php

namespace App\Controller;

use App\Entity\Reservation;
use App\Entity\Vehicule;
use App\Entity\User;
use App\Form\ReservationType;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/reservation')]
final class ReservationController extends AbstractController
{
    #[Route(name: 'app_reservation_index', methods: ['GET'])]
    public function index(ReservationRepository $reservationRepository): Response
    {
        return $this->render('reservation/index.html.twig', [
            'reservations' => $reservationRepository->findAll(),
        ]);
    }

    #[Route('/new/{id_vehicule}/{id_user}', name: 'app_reservation_new', methods: ['GET', 'POST'])]
    public function new(int $id_vehicule, int $id_user, Request $request, EntityManagerInterface $entityManager): Response
    {
        $vehicule = $entityManager->getRepository(Vehicule::class)->find($id_vehicule);
        $user = $entityManager->getRepository(User::class)->find($id_user);

        if (!$vehicule || !$user) {
            throw $this->createNotFoundException('Véhicule ou utilisateur introuvable.');
        }

        $reservation = new Reservation();
        $reservation->setVehicule($vehicule);
        $reservation->setUser($user);
        $reservation->setStatus('En attente');

        $vehicule->setIsDisponible(false);
        $entityManager->persist($vehicule);

        $form = $this->createForm(ReservationType::class, $reservation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $beginDate = $reservation->getBeginDate();
            $endDate = $reservation->getEndDate();

            if ($endDate < $beginDate) {
                $this->addFlash('error', 'La date de fin doit être après la date de début.');
                return $this->redirectToRoute('app_reservation_new', [
                    'id_vehicule' => $vehicule->getId(),
                    'id_user' => $user->getId(),
                ]);
            }

            $daysCount = $beginDate->diff($endDate)->days;
            $totalPrice = $vehicule->getDayPrice() * $daysCount;
            $reservation->setTotalPrice($totalPrice);

            $entityManager->persist($reservation);
            $entityManager->flush();

            return $this->redirectToRoute('app_reservation_user', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('reservation/new.html.twig', [
            'reservation' => $reservation,
            'form' => $form->createView(),
        ]);
    }

    
    #[Route('/{id<\d+>}', name: 'app_reservation_show', methods: ['GET'])]
    public function show(int $id, EntityManagerInterface $entityManager): Response
    {
        $reservation = $entityManager->getRepository(Reservation::class)->find($id);
    
        if (!$reservation) {
            throw $this->createNotFoundException('Réservation non trouvée.');
        }
    
        return $this->render('reservation/show.html.twig', [
            'reservation' => $reservation,
        ]);
    }
    

    #[Route('/mes-reservations', name: 'app_reservation_user', methods: ['GET'])]
    public function userReservations(ReservationRepository $reservationRepository): Response
    {
        $user = $this->getUser();

        if (!$user) {
            throw $this->createAccessDeniedException('Vous devez être connecté pour voir vos réservations.');
        }

        $reservations = $reservationRepository->findBy(['user' => $user]);

        return $this->render('reservation/user_reservations.html.twig', [
            'reservations' => $reservations,
        ]);
    }

    #[Route('/{id}/conclure', name: 'app_reservation_conclure', methods: ['GET'])]
    public function conclure(int $id, EntityManagerInterface $entityManager): Response
    {
        $reservation = $entityManager->getRepository(Reservation::class)->find($id);
        
        if (!$reservation) {
            throw $this->createNotFoundException('Réservation non trouvée.');
        }
        $vehicule = $reservation->getVehicule();
        $vehicule->setIsDisponible(true);
        $entityManager->persist($vehicule);

        $reservation->setStatus('Terminée');
        $entityManager->persist($reservation);
        $entityManager->flush();

        return $this->redirectToRoute('app_reservation_user', [], Response::HTTP_SEE_OTHER);
    }
}
