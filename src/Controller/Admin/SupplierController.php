<?php

namespace App\Controller\Admin;

use App\Entity\Supplier;
use App\Form\SupplierType;
use App\Repository\SubAgreementSupplierAssignmentRepository;
use App\Repository\SupplierRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Lists, creates, changes and deletes the suppliers.
 */
#[IsGranted('ROLE_MANAGE_SUPPLIERS')]
#[Route('/admin/suppliers')]
final class SupplierController extends AbstractController
{
    #[Route('', name: 'admin_supplier_index', methods: ['GET'])]
    public function index(SupplierRepository $suppliers): Response
    {
        return $this->render('admin/supplier/index.html.twig', [
            'suppliers' => $suppliers->findBy([], ['name' => 'ASC']),
        ]);
    }

    #[Route('/new', name: 'admin_supplier_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        return $this->edit($request, new Supplier(), $entityManager);
    }

    #[Route('/{id}/edit', name: 'admin_supplier_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Supplier $supplier, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(SupplierType::class, $supplier);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($supplier);
            $entityManager->flush();
            $this->addFlash('success', 'supplier.saved');

            return $this->redirectToRoute('admin_supplier_index');
        }

        return $this->render('admin/supplier/edit.html.twig', ['form' => $form, 'supplier' => $supplier]);
    }

    /**
     * A supplier with assignments is not deleted, because the old figures refer to it.
     */
    #[Route('/{id}/delete', name: 'admin_supplier_delete', methods: ['POST'])]
    public function delete(Request $request, Supplier $supplier, EntityManagerInterface $entityManager, SubAgreementSupplierAssignmentRepository $assignments): Response
    {
        if (!$this->isCsrfTokenValid('delete-supplier-'.$supplier->getId(), $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException('The CSRF token is not valid.');
        }

        if ($assignments->countBySupplier($supplier) > 0) {
            $this->addFlash('error', 'supplier.delete_blocked');
        } else {
            $entityManager->remove($supplier);
            $entityManager->flush();
            $this->addFlash('success', 'supplier.deleted');
        }

        return $this->redirectToRoute('admin_supplier_index');
    }
}
