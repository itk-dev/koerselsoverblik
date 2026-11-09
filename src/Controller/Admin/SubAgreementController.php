<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Clock\LocalCalendar;
use App\Entity\SubAgreement;
use App\Form\SubAgreementType;
use App\Repository\SubAgreementRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Lists and creates the sub-agreements, and shows the routes and the assignments of one.
 */
#[IsGranted('ROLE_MANAGE_SUPPLIERS')]
#[Route('/admin/sub-agreements')]
final class SubAgreementController extends AbstractController
{
    #[Route('', name: 'admin_sub_agreement_index', methods: ['GET'])]
    public function index(SubAgreementRepository $subAgreements, LocalCalendar $calendar): Response
    {
        $today = $calendar->today();

        return $this->render('admin/sub_agreement/index.html.twig', [
            'sub_agreements' => $subAgreements->findAllWithoutCurrentAssignmentFirst($today),
            'today' => $today,
        ]);
    }

    #[Route('/new', name: 'admin_sub_agreement_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $subAgreement = new SubAgreement();
        $form = $this->createForm(SubAgreementType::class, $subAgreement);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($subAgreement);
            $entityManager->flush();
            $this->addFlash('success', 'sub_agreement.created');

            return $this->redirectToRoute('admin_sub_agreement_show', ['id' => $subAgreement->getId()]);
        }

        return $this->render('admin/sub_agreement/new.html.twig', ['form' => $form]);
    }

    #[Route('/{id}', name: 'admin_sub_agreement_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(SubAgreement $subAgreement): Response
    {
        return $this->render('admin/sub_agreement/show.html.twig', ['sub_agreement' => $subAgreement]);
    }
}
