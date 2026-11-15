<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\SubAgreement;
use App\Form\SubAgreementType;
use App\Import\MonthCitizensBuilder;
use App\Repository\SubAgreementRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Lists, creates and changes the sub-agreements, and shows the supplier and the routes of one.
 */
#[IsGranted('ROLE_MANAGE_SUPPLIERS')]
#[Route('/admin/sub-agreements')]
final class SubAgreementController extends AbstractController
{
    #[Route('', name: 'admin_sub_agreement_index', methods: ['GET'])]
    public function index(SubAgreementRepository $subAgreements): Response
    {
        return $this->render('admin/sub_agreement/index.html.twig', [
            'sub_agreements' => $subAgreements->findAllNeedingSupplierFirst(),
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

    #[Route('/{id}/edit', name: 'admin_sub_agreement_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Request $request, SubAgreement $subAgreement, EntityManagerInterface $entityManager, MonthCitizensBuilder $monthCitizens): Response
    {
        $wasIgnored = $subAgreement->isIgnored();
        $oldSupplierId = $subAgreement->getSupplier()?->getId();
        $form = $this->createForm(SubAgreementType::class, $subAgreement, ['edit_reference' => false]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            // The citizens of each month depend on the flag and the supplier. A new sub-agreement
            // has no routes until an import, so only an edit builds them again.
            $entityManager->wrapInTransaction(static function () use ($entityManager, $monthCitizens, $subAgreement, $wasIgnored, $oldSupplierId): void {
                $entityManager->flush();
                $monthCitizens->rebuildAfterSubAgreementChange($wasIgnored !== $subAgreement->isIgnored(), $oldSupplierId, $subAgreement->getSupplier()?->getId());
            });
            $this->addFlash('success', 'sub_agreement.saved');

            return $this->redirectToRoute('admin_sub_agreement_show', ['id' => $subAgreement->getId()]);
        }

        return $this->render('admin/sub_agreement/edit.html.twig', ['form' => $form, 'sub_agreement' => $subAgreement]);
    }
}
