<?php

namespace AppBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Form\FormError;
use AppBundle\Entity\Team;
use AppBundle\Form\TeamType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\HttpFoundation\JsonResponse;

class TeamController extends Controller
{
    public function indexAction()
    {
        $em = $this->getDoctrine()->getManager();
        $user = $this->getUser();
        $teams = array();
        if ($user) {
            $teams = $em->getRepository('AppBundle:Team')->findBy(array('owner' => $user));
        }

        // prepare decoded meta (hero_meta and meta) for each deck so templates can use it
        $deckMetaMap = [];
        foreach ($teams as $team) {
            foreach ($team->getDecks() as $deck) {
                try {
                    $heroMeta = json_decode($deck->getCharacter()->getMeta());
                } catch (\Exception $e) {
                    $heroMeta = null;
                }
                try {
                    $meta = json_decode($deck->getMeta());
                } catch (\Exception $e) {
                    $meta = null;
                }
                $deckMetaMap[$deck->getId()] = ['hero_meta' => $heroMeta, 'meta' => $meta];
            }
        }

        // faction/aspect color mapping (fallback when hero_meta colors are missing)
        $factionColors = [
            'leadership' => '#2b80c5',
            'aggression' => '#cc3038',
            'protection' => '#107116',
            'basic' => '#808080',
            'justice' => '#c0c000',
            'pool' => '#d074ac',
            'determination' => '#493f64',
            'hero' => '#AB006A'
        ];

        // build a gradient CSS string per team using decks' primary colors (hero_meta.colors[0])
        $teamGradientMap = [];
        foreach ($teams as $team) {
            $colors = [];
            foreach ($team->getDecks() as $deck) {
                $dm = isset($deckMetaMap[$deck->getId()]) ? $deckMetaMap[$deck->getId()] : null;
                $color = null;
                // prefer hero meta primary color
                if ($dm && isset($dm['hero_meta']->colors) && is_array($dm['hero_meta']->colors) && isset($dm['hero_meta']->colors[0])) {
                    $color = $dm['hero_meta']->colors[0];
                }
                // fallback to meta.aspect
                if (!$color && $dm && isset($dm['meta']->aspect)) {
                    $aspect = $dm['meta']->aspect;
                    if (is_string($aspect) && isset($factionColors[$aspect])) {
                        $color = $factionColors[$aspect];
                    }
                }
                // final fallback to deck character faction code
                if (!$color) {
                    try {
                        $faction = $deck->getCharacter()->getFaction();
                        if ($faction && method_exists($faction, 'getCode')) {
                            $code = $faction->getCode();
                            if ($code && isset($factionColors[$code])) {
                                $color = $factionColors[$code];
                            }
                        }
                    } catch (\Exception $e) {
                        // ignore
                    }
                }

                if ($color) {
                    // preserve order but keep unique
                    if (!in_array($color, $colors)) {
                        $colors[] = $color;
                    }
                }
            }

            // helper to pick readable text color (#000 or #fff) from a hex color
            $pickTextColorFromHex = function ($hex) {
                if (!$hex) return '#ffffff';
                $h = ltrim($hex, '#');
                if (strlen($h) == 3) {
                    $r = hexdec(str_repeat($h[0], 2));
                    $g = hexdec(str_repeat($h[1], 2));
                    $b = hexdec(str_repeat($h[2], 2));
                } else {
                    $r = hexdec(substr($h, 0, 2));
                    $g = hexdec(substr($h, 2, 2));
                    $b = hexdec(substr($h, 4, 2));
                }
                $rs = $r / 255; $gs = $g / 255; $bs = $b / 255;
                $rs = ($rs <= 0.03928) ? $rs / 12.92 : pow(($rs + 0.055) / 1.055, 2.4);
                $gs = ($gs <= 0.03928) ? $gs / 12.92 : pow(($gs + 0.055) / 1.055, 2.4);
                $bs = ($bs <= 0.03928) ? $bs / 12.92 : pow(($bs + 0.055) / 1.055, 2.4);
                $l = 0.2126 * $rs + 0.7152 * $gs + 0.0722 * $bs;
                return ($l > 0.179) ? '#000000' : '#ffffff';
            };

            // we'll try to prefer a hero-provided text color, but only if contrast is sufficient
            $textColor = null;

            // build CSS gradient: if 0 colors => null, if 1 => solid color, else linear-gradient spread
            if (count($colors) === 0) {
                $bg = null;
            } elseif (count($colors) === 1) {
                $bg = $colors[0];
            } else {
                $n = count($colors);
                $stops = [];
                for ($i = 0; $i < $n; $i++) {
                    $pos = intval(round(($i / ($n - 1)) * 100));
                    $stops[] = $colors[$i] . ' ' . $pos . '%';
                }
                $bg = 'linear-gradient(90deg, ' . implode(', ', $stops) . ')';
            }

            // try hero-provided text color but only keep it if contrast vs primary bg is acceptable
            $heroCandidate = null;
            foreach ($team->getDecks() as $deck) {
                $dm2 = isset($deckMetaMap[$deck->getId()]) ? $deckMetaMap[$deck->getId()] : null;
                if ($dm2 && isset($dm2['hero_meta']->colors) && is_array($dm2['hero_meta']->colors) && isset($dm2['hero_meta']->colors[3]) && $dm2['hero_meta']->colors[3]) {
                    $heroCandidate = $dm2['hero_meta']->colors[3];
                    break;
                }
            }

            // helper: convert hex to rgb array
            $hexToRgb = function ($hex) {
                $h = ltrim($hex, '#');
                if (strlen($h) == 3) {
                    $r = hexdec(str_repeat($h[0], 2));
                    $g = hexdec(str_repeat($h[1], 2));
                    $b = hexdec(str_repeat($h[2], 2));
                } else {
                    $r = hexdec(substr($h, 0, 2));
                    $g = hexdec(substr($h, 2, 2));
                    $b = hexdec(substr($h, 4, 2));
                }
                return [$r, $g, $b];
            };

            // helper: relative luminance
            $relLum = function ($hex) use ($hexToRgb) {
                list($r, $g, $b) = $hexToRgb($hex);
                $rs = $r / 255; $gs = $g / 255; $bs = $b / 255;
                $rs = ($rs <= 0.03928) ? $rs / 12.92 : pow(($rs + 0.055) / 1.055, 2.4);
                $gs = ($gs <= 0.03928) ? $gs / 12.92 : pow(($gs + 0.055) / 1.055, 2.4);
                $bs = ($bs <= 0.03928) ? $bs / 12.92 : pow(($bs + 0.055) / 1.055, 2.4);
                return 0.2126 * $rs + 0.7152 * $gs + 0.0722 * $bs;
            };

            // helper: contrast ratio
            $contrastRatio = function ($hex1, $hex2) use ($relLum) {
                $l1 = $relLum($hex1);
                $l2 = $relLum($hex2);
                if ($l1 < $l2) { $tmp = $l1; $l1 = $l2; $l2 = $tmp; }
                return ($l1 + 0.05) / ($l2 + 0.05);
            };

            // determine sample color to compare with (first color of gradient, or single color)
            if ($bg === null) {
                $sample = '#000000';
            } elseif (strpos($bg, 'linear-gradient') === 0) {
                if (preg_match('/#([0-9a-fA-F]{3,6})/', $bg, $m)) {
                    $sample = '#' . $m[1];
                } else {
                    $sample = '#000000';
                }
            } else {
                $sample = $bg;
            }

            // WCAG threshold: require at least 4.5:1 contrast for normal text
            if ($heroCandidate) {
                try {
                    $ratio = $contrastRatio($heroCandidate, $sample);
                    if ($ratio >= 4.5) {
                        $textColor = $heroCandidate;
                    }
                } catch (\Exception $e) {
                    // ignore and fall back
                }
            }

            // if no explicit acceptable hero text color, pick a readable one from the primary bg
            if (!$textColor) {
                if ($bg === null) {
                    $textColor = '#ffffff';
                } else {
                    $textColor = $pickTextColorFromHex($sample);
                }
            }

            $teamGradientMap[$team->getId()] = ['bg' => $bg, 'text' => $textColor, 'sample' => $sample];
        }

    return $this->render('AppBundle:Team:team.index.html.twig', array('teams' => $teams, 'deck_meta_map' => $deckMetaMap, 'team_gradient_map' => $teamGradientMap));
    }

    public function newAction(Request $request)
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $user = $this->getUser();

    $team = new Team();
    // set owner early so validation (NotNull on owner) passes during form validation
    $team->setOwner($user);
    // default visibility to private
    $team->setVisibility('private');
    $em = $this->getDoctrine()->getManager();
    // limit decks choices to the current user's decks (do not pre-filter by visibility here)
    // keep all user's decks available so the client-side visibility filter can show/hide published vs private decks dynamically
    $userDecks = $em->getRepository('AppBundle:Deck')->findBy(['user' => $user]);

    $form = $this->createForm('AppBundle\\Form\\TeamType', $team);
        // ensure visibility default is set on the form as well (in case form rendering overrides)
        if ($form->has('visibility')) {
            try {
                $form->get('visibility')->setData($team->getVisibility());
            } catch (\Exception $e) {
                // ignore
            }
        }
        // override the decks field so only user's decks are selectable
        $form->add('decks', EntityType::class, array(
            'class' => 'AppBundle:Deck',
            'choice_label' => 'name',
            'multiple' => true,
            'expanded' => false,
            'required' => true,
            'choices' => $userDecks,
            'choice_attr' => function($choice, $key, $value) {
                try {
                    $isPublished = ($choice->getParent() !== null);
                } catch (\Exception $e) {
                    $isPublished = false;
                }
                return ['data-published' => $isPublished ? '1' : '0'];
            },
        ));
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // ensure slug exists
            if (!$team->getSlug()) {
                $slug = preg_replace('/[^a-z0-9]+/', '-', strtolower(trim($team->getName())));
                $slug = trim($slug, '-');
                $team->setSlug($slug ?: 'team-' . time());
            }

            $em = $this->getDoctrine()->getManager();
            $em->persist($team);
            $em->flush();

            return $this->redirect($this->generateUrl('team_view', array('team_id' => $team->getId(), 'slug' => $team->getSlug())));
        }

        // prepare deck choices data (meta + hero_meta) for template visual rendering
        $deckChoices = [];
        foreach ($userDecks as $d) {
            try {
                $heroMeta = json_decode($d->getCharacter()->getMeta());
            } catch (\Exception $e) {
                $heroMeta = null;
            }
            try {
                $meta = json_decode($d->getMeta());
            } catch (\Exception $e) {
                $meta = null;
            }
            $deckChoices[] = ['decklist' => $d, 'meta' => $meta, 'hero_meta' => $heroMeta];
        }

    return $this->render('AppBundle:Team:team.form.html.twig', array('form' => $form->createView(), 'deck_choices' => $deckChoices));
    }

    public function viewAction($team_id, $slug = null)
    {
        $em = $this->getDoctrine()->getManager();
        $team = $em->getRepository('AppBundle:Team')->find($team_id);
        if (!$team) {
            throw $this->createNotFoundException('Team not found');
        }

        // prepare decoded meta (hero_meta and meta) for each deck so template can use it
        $deckMetaMap = [];
        foreach ($team->getDecks() as $deck) {
            try {
                $heroMeta = json_decode($deck->getCharacter()->getMeta());
            } catch (\Exception $e) {
                $heroMeta = null;
            }
            try {
                $meta = json_decode($deck->getMeta());
            } catch (\Exception $e) {
                $meta = null;
            }
            $deckMetaMap[$deck->getId()] = ['hero_meta' => $heroMeta, 'meta' => $meta];
        }

        // faction/aspect color mapping (fallback when hero_meta colors are missing)
        $factionColors = [
            'leadership' => '#2b80c5',
            'aggression' => '#cc3038',
            'protection' => '#107116',
            'basic' => '#808080',
            'justice' => '#c0c000',
            'pool' => '#d074ac',
            'determination' => '#493f64',
            'hero' => '#AB006A'
        ];

        // build a gradient CSS string for this team using decks' primary colors (hero_meta.colors[0])
        $colors = [];
        foreach ($team->getDecks() as $deck) {
            $dm = isset($deckMetaMap[$deck->getId()]) ? $deckMetaMap[$deck->getId()] : null;
            $color = null;
            // prefer hero meta primary color
            if ($dm && isset($dm['hero_meta']->colors) && is_array($dm['hero_meta']->colors) && isset($dm['hero_meta']->colors[0])) {
                $color = $dm['hero_meta']->colors[0];
            }
            // fallback to meta.aspect
            if (!$color && $dm && isset($dm['meta']->aspect)) {
                $aspect = $dm['meta']->aspect;
                if (is_string($aspect) && isset($factionColors[$aspect])) {
                    $color = $factionColors[$aspect];
                }
            }
            // final fallback to deck character faction code
            if (!$color) {
                try {
                    $faction = $deck->getCharacter()->getFaction();
                    if ($faction && method_exists($faction, 'getCode')) {
                        $code = $faction->getCode();
                        if ($code && isset($factionColors[$code])) {
                            $color = $factionColors[$code];
                        }
                    }
                } catch (\Exception $e) {
                    // ignore
                }
            }

            if ($color) {
                if (!in_array($color, $colors)) {
                    $colors[] = $color;
                }
            }
        }

        // helper to pick readable text color (#000 or #fff) from a hex color
        $pickTextColorFromHex = function ($hex) {
            if (!$hex) return '#ffffff';
            $h = ltrim($hex, '#');
            if (strlen($h) == 3) {
                $r = hexdec(str_repeat($h[0], 2));
                $g = hexdec(str_repeat($h[1], 2));
                $b = hexdec(str_repeat($h[2], 2));
            } else {
                $r = hexdec(substr($h, 0, 2));
                $g = hexdec(substr($h, 2, 2));
                $b = hexdec(substr($h, 4, 2));
            }
            $rs = $r / 255; $gs = $g / 255; $bs = $b / 255;
            $rs = ($rs <= 0.03928) ? $rs / 12.92 : pow(($rs + 0.055) / 1.055, 2.4);
            $gs = ($gs <= 0.03928) ? $gs / 12.92 : pow(($gs + 0.055) / 1.055, 2.4);
            $bs = ($bs <= 0.03928) ? $bs / 12.92 : pow(($bs + 0.055) / 1.055, 2.4);
            $l = 0.2126 * $rs + 0.7152 * $gs + 0.0722 * $bs;
            return ($l > 0.179) ? '#000000' : '#ffffff';
        };

        $textColor = null;

        if (count($colors) === 0) {
            $bg = null;
        } elseif (count($colors) === 1) {
            $bg = $colors[0];
        } else {
            $n = count($colors);
            $stops = [];
            for ($i = 0; $i < $n; $i++) {
                $pos = intval(round(($i / ($n - 1)) * 100));
                $stops[] = $colors[$i] . ' ' . $pos . '%';
            }
            $bg = 'linear-gradient(90deg, ' . implode(', ', $stops) . ')';
        }

        // try hero-provided text color but only keep it if contrast vs primary bg is acceptable
        $heroCandidate = null;
        foreach ($team->getDecks() as $deck) {
            $dm2 = isset($deckMetaMap[$deck->getId()]) ? $deckMetaMap[$deck->getId()] : null;
            if ($dm2 && isset($dm2['hero_meta']->colors) && is_array($dm2['hero_meta']->colors) && isset($dm2['hero_meta']->colors[3]) && $dm2['hero_meta']->colors[3]) {
                $heroCandidate = $dm2['hero_meta']->colors[3];
                break;
            }
        }

        $hexToRgb = function ($hex) {
            $h = ltrim($hex, '#');
            if (strlen($h) == 3) {
                $r = hexdec(str_repeat($h[0], 2));
                $g = hexdec(str_repeat($h[1], 2));
                $b = hexdec(str_repeat($h[2], 2));
            } else {
                $r = hexdec(substr($h, 0, 2));
                $g = hexdec(substr($h, 2, 2));
                $b = hexdec(substr($h, 4, 2));
            }
            return [$r, $g, $b];
        };

        $relLum = function ($hex) use ($hexToRgb) {
            list($r, $g, $b) = $hexToRgb($hex);
            $rs = $r / 255; $gs = $g / 255; $bs = $b / 255;
            $rs = ($rs <= 0.03928) ? $rs / 12.92 : pow(($rs + 0.055) / 1.055, 2.4);
            $gs = ($gs <= 0.03928) ? $gs / 12.92 : pow(($gs + 0.055) / 1.055, 2.4);
            $bs = ($bs <= 0.03928) ? $bs / 12.92 : pow(($bs + 0.055) / 1.055, 2.4);
            return 0.2126 * $rs + 0.7152 * $gs + 0.0722 * $bs;
        };

        $contrastRatio = function ($hex1, $hex2) use ($relLum) {
            $l1 = $relLum($hex1);
            $l2 = $relLum($hex2);
            if ($l1 < $l2) { $tmp = $l1; $l1 = $l2; $l2 = $tmp; }
            return ($l1 + 0.05) / ($l2 + 0.05);
        };

        if ($bg === null) {
            $sample = '#000000';
        } elseif (strpos($bg, 'linear-gradient') === 0) {
            if (preg_match('/#([0-9a-fA-F]{3,6})/', $bg, $m)) {
                $sample = '#' . $m[1];
            } else {
                $sample = '#000000';
            }
        } else {
            $sample = $bg;
        }

        if ($heroCandidate) {
            try {
                $ratio = $contrastRatio($heroCandidate, $sample);
                if ($ratio >= 4.5) {
                    $textColor = $heroCandidate;
                }
            } catch (\Exception $e) {
                // ignore and fall back
            }
        }

        if (!$textColor) {
            if ($bg === null) {
                $textColor = '#ffffff';
            } else {
                $textColor = $pickTextColorFromHex($sample);
            }
        }

        $teamGradientMap = [];
    $teamGradientMap[$team->getId()] = ['bg' => $bg, 'text' => $textColor, 'sample' => $sample];

        // compute card presence counts across all decks in the team (skip hero / character cards)
        $cardCounts = [];
        // also build per-deck groups of slots by card type (skip hero/character cards)
        $deckSlotGroups = [];
    // map deckId -> cardCode -> faction_code for templating when faction relation is not accessible
    $deckSlotFactionMap = [];
    // map deckId -> cardCode -> type_code for templating when type relation is not accessible
    $deckSlotTypeMap = [];
        foreach ($team->getDecks() as $deck) {
            foreach ($deck->getSlots() as $slot) {
                try {
                    $card = $slot->getCard();
                } catch (\Exception $e) {
                    continue;
                }
                if (!$card) continue;
                $code = $card->getCode();
                // skip the deck's hero/character card
                try {
                    $char = $deck->getCharacter();
                    if ($char && $code == $char->getCode()) continue;
                } catch (\Exception $e) {
                    // ignore
                }
                // skip cards whose type is 'hero' or 'character' if such types exist
                try {
                    $type = $card->getType();
                    $typeCode = $type ? $type->getCode() : null;
                    if ($typeCode === 'hero' || $typeCode === 'character') continue;
                } catch (\Exception $e) {
                    // ignore
                }

                // skip cards whose faction is 'hero' (explicit request)
                try {
                    $cardFaction = $card->getFaction();
                    $cardFactionCode = $cardFaction ? (method_exists($cardFaction, 'getCode') ? $cardFaction->getCode() : null) : null;
                    if ($cardFactionCode === 'hero') continue;
                } catch (\Exception $e) {
                    // ignore
                }

                // count presence per deck
                if (!isset($cardCounts[$code])) $cardCounts[$code] = 0;
                $cardCounts[$code] += 1;

                // group slot by type for this deck
                try {
                    $type = $card->getType();
                } catch (\Exception $e) {
                    $type = null;
                }
                $typeName = null;
                if ($type) {
                    if (method_exists($type, 'getName') && $type->getName()) {
                        $typeName = $type->getName();
                    } elseif (method_exists($type, 'getCode') && $type->getCode()) {
                        $typeName = $type->getCode();
                    }
                    // ensure the Type object exposes a 'code' property for Twig access (slot.card.type.code)
                    try {
                        if (method_exists($type, 'getCode')) {
                            $tcode = $type->getCode();
                            // attach as public property so Twig can read slot.card.type.code even if proxy blocks method access
                            $type->code = $tcode;
                        }
                    } catch (\Exception $e) {
                        // ignore
                    }
                }
                if (!$typeName) $typeName = 'Other';

                if (!isset($deckSlotGroups[$deck->getId()])) $deckSlotGroups[$deck->getId()] = [];
                if (!isset($deckSlotGroups[$deck->getId()][$typeName])) $deckSlotGroups[$deck->getId()][$typeName] = [];
                // keep Slot entity objects so templates/macros can access slot.card.faction.code
                $deckSlotGroups[$deck->getId()][$typeName][] = $slot;
                // store available faction code by deck and card code for templates as a fallback
                if (!isset($deckSlotFactionMap[$deck->getId()])) $deckSlotFactionMap[$deck->getId()] = [];
                if ($cardFactionCode) {
                    $deckSlotFactionMap[$deck->getId()][$code] = $cardFactionCode;
                }
                // store card type code as a fallback for templates
                try {
                    $cardType = $card->getType();
                    $cardTypeCode = $cardType ? (method_exists($cardType, 'getCode') ? $cardType->getCode() : null) : null;
                } catch (\Exception $e) {
                    $cardTypeCode = null;
                }
                if (!isset($deckSlotTypeMap[$deck->getId()])) $deckSlotTypeMap[$deck->getId()] = [];
                if ($cardTypeCode) {
                    $deckSlotTypeMap[$deck->getId()][$code] = $cardTypeCode;
                }
            }
        }

        // sort groups by type name alphabetically for each deck
        if (!empty($deckSlotGroups)) {
            foreach ($deckSlotGroups as $did => $groups) {
                if (is_array($groups)) {
                    ksort($deckSlotGroups[$did], SORT_NATURAL | SORT_FLAG_CASE);
                }
            }
        }

        $sharedCardCodes = [];
        foreach ($cardCounts as $code => $count) {
            if ($count > 1) $sharedCardCodes[] = $code;
        }

        // No hard-coded campaign display — campaign definitions are managed via import and per-team campaignlists.

        // load available static campaigns for selection in the team view
        $campaignRepo = $this->getDoctrine()->getRepository('AppBundle:Campaign');
        $availableCampaigns = $campaignRepo->findAll();

        // load existing campaignlist rows for this team so the view can show them
        $campaignListRepo = $this->getDoctrine()->getRepository('AppBundle:CampaignList');
        $teamCampaignLists = $campaignListRepo->findBy(['team' => $team]);

        // build decoded campaign definitions map for each campaign attached to the team
        $campaignDefs = [];
        // also build decoded campaignlist values map so templates can render existing values
        $campaignlistValues = [];
        foreach ($teamCampaignLists as $cl) {
            $c = $cl->getCampaign();
            if (!$c) continue;
            $cid = $c->getId();
            if (!isset($campaignDefs[$cid])) {
                // decode scenarios and index them by scenario code for easy lookup in templates
                $rawScenarios = $c->getScenarios() ? json_decode($c->getScenarios(), true) : [];
                $scenariosByCode = [];
                if (is_array($rawScenarios)) {
                    foreach ($rawScenarios as $s) {
                        if (is_array($s) && isset($s['code'])) {
                            $scenariosByCode[$s['code']] = $s;
                        }
                    }
                }

                // decode campaign-level modulars mapping and attach per-scenario modulars
                $rawModulars = null;
                if (method_exists($c, 'getModulars') && $c->getModulars()) {
                    $rawModulars = json_decode($c->getModulars(), true);
                }
                if (!is_array($rawModulars)) $rawModulars = [];
                foreach ($scenariosByCode as $scode => &$sentry) {
                    if (!is_array($sentry)) $sentry = (array) $sentry;
                    $sentry['modulars'] = isset($rawModulars[$scode]) ? $rawModulars[$scode] : [];
                }
                unset($sentry);

                // detect whether notes/counters are campaign-level (list of keys) or scenario-level (map)
                $rawNotes = null;
                if (method_exists($c, 'getCampaignNotes') && $c->getCampaignNotes()) {
                    $rawNotes = json_decode($c->getCampaignNotes(), true);
                } elseif ($c->getScenarioNotes()) {
                    $rawNotes = json_decode($c->getScenarioNotes(), true);
                }
                $rawCounters = null;
                if (method_exists($c, 'getCampaignCounters') && $c->getCampaignCounters()) {
                    $rawCounters = json_decode($c->getCampaignCounters(), true);
                } elseif ($c->getScenarioCounters()) {
                    $rawCounters = json_decode($c->getScenarioCounters(), true);
                }

                $campaignNotes = [];
                $scenarioNotes = [];
                if (is_array($rawNotes)) {
                    // numeric-indexed array => campaign-level list
                    if (array_values($rawNotes) === $rawNotes && count($rawNotes) && is_string($rawNotes[0])) {
                        $campaignNotes = $rawNotes;
                    } else {
                        $scenarioNotes = $rawNotes;
                    }
                }

                $campaignCounters = [];
                $scenarioCounters = [];
                if (is_array($rawCounters)) {
                    if (array_values($rawCounters) === $rawCounters && count($rawCounters) && is_string($rawCounters[0])) {
                        $campaignCounters = $rawCounters;
                    } else {
                        $scenarioCounters = $rawCounters;
                    }
                }

                $campaignDefs[$cid] = [
                    'scenarios' => $scenariosByCode,
                    'campaign_notes' => $campaignNotes,
                    'campaign_counters' => $campaignCounters,
                    'scenario_notes' => $scenarioNotes,
                    'scenario_counters' => $scenarioCounters,
                    'team_hps' => (method_exists($c, 'getTeamHps') && $c->getTeamHps()) ? json_decode($c->getTeamHps(), true) : null,
                ];
            }
            // decode campaignlist stored values; support both campaign-level and scenario-level shapes
            $rawClNotes = $cl->getCampaignNotes() ? json_decode($cl->getCampaignNotes(), true) : null;
            $rawClCounters = $cl->getCampaignCounters() ? json_decode($cl->getCampaignCounters(), true) : null;

            $clCampaignNotes = [];
            $clScenarioNotes = [];
            if (is_array($rawClNotes)) {
                // if numeric-indexed list of keys => campaign-level
                if (array_values($rawClNotes) === $rawClNotes && count($rawClNotes) && is_string(reset($rawClNotes))) {
                    foreach ($rawClNotes as $k) { $clCampaignNotes[$k] = ''; }
                } else {
                    $clScenarioNotes = $rawClNotes;
                }
            }

            $clCampaignCounters = [];
            $clScenarioCounters = [];
            if (is_array($rawClCounters)) {
                if (array_values($rawClCounters) === $rawClCounters && count($rawClCounters) && is_string(reset($rawClCounters))) {
                    foreach ($rawClCounters as $k) { $clCampaignCounters[$k] = 0; }
                } else {
                    $clScenarioCounters = $rawClCounters;
                }
            }

            // decode player names
            $rawClPlayers = $cl->getPlayerNames() ? json_decode($cl->getPlayerNames(), true) : null;
            $clPlayers = is_array($rawClPlayers) ? $rawClPlayers : [];

            $campaignlistValues[$cl->getId()] = [
                'campaign_notes' => $clCampaignNotes,
                'campaign_counters' => $clCampaignCounters,
                'scenario_notes' => $clScenarioNotes,
                'scenario_counters' => $clScenarioCounters,
                'team_hps' => $cl->getTeamHps() ? json_decode($cl->getTeamHps(), true) : null,
                'campaign_cards' => $cl->getCampaignCards() ? json_decode($cl->getCampaignCards(), true) : null,
                'player_names' => $clPlayers,
                'selected_scenario' => ($cl->getSelectedScenario() !== null ? intval($cl->getSelectedScenario()) : 0),
            ];
        }

        return $this->render('AppBundle:Team:team.show.html.twig', array(
                'team' => $team,
                'deck_meta_map' => $deckMetaMap,
                'team_gradient_map' => $teamGradientMap,
                'shared_card_codes' => $sharedCardCodes,
                'deck_slot_groups_map' => $deckSlotGroups,
                'deck_slot_faction_map' => $deckSlotFactionMap,
                'deck_slot_type_map' => $deckSlotTypeMap,
                // no hard-coded campaign payload
                'available_campaigns' => $availableCampaigns,
                'team_campaignlists' => $teamCampaignLists,
                'campaign_defs' => $campaignDefs,
                'campaignlist_values' => $campaignlistValues,
            ));
    }

    public function editAction(Request $request, $team_id)
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $user = $this->getUser();
        $em = $this->getDoctrine()->getManager();
        $team = $em->getRepository('AppBundle:Team')->find($team_id);
        if (!$team) {
            throw $this->createNotFoundException('Team not found');
        }
        if (!$this->isGranted('EDIT', $team)) {
            throw $this->createAccessDeniedException();
        }

        // limit decks choices to the current user's decks; do not pre-filter by visibility so client-side filter can act
        $userDecks = $em->getRepository('AppBundle:Deck')->findBy(['user' => $user]);

        $form = $this->createForm('AppBundle\\Form\\TeamType', $team);
            // ensure visibility field is populated from the team entity when editing
            if ($form->has('visibility')) {
                try {
                    $form->get('visibility')->setData($team->getVisibility());
                } catch (\Exception $e) {
                    // ignore
                }
            }
        // ensure decks selection is limited to user's decks
        $form->add('decks', EntityType::class, array(
            'class' => 'AppBundle:Deck',
            'choice_label' => 'name',
            'multiple' => true,
            'expanded' => false,
            'required' => true,
            'choices' => $userDecks,
            'choice_attr' => function($choice, $key, $value) {
                try {
                    $isPublished = ($choice->getParent() !== null);
                } catch (\Exception $e) {
                    $isPublished = false;
                }
                return ['data-published' => $isPublished ? '1' : '0'];
            },
        ));
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $decks = $team->getDecks();
            $count = count($decks);
            // slug generation if needed
            if (!$team->getSlug()) {
                $slug = preg_replace('/[^a-z0-9]+/', '-', strtolower(trim($team->getName())));
                $slug = trim($slug, '-');
                $team->setSlug($slug ?: 'team-' . time());
            }

            $em->persist($team);
            $em->flush();
            return $this->redirect($this->generateUrl('team_view', array('team_id' => $team->getId(), 'slug' => $team->getSlug())));
        }

        // prepare deck choices data (meta + hero_meta) for template visual rendering
        $deckChoices = [];
        foreach ($userDecks as $d) {
            try {
                $heroMeta = json_decode($d->getCharacter()->getMeta());
            } catch (\Exception $e) {
                $heroMeta = null;
            }
            try {
                $meta = json_decode($d->getMeta());
            } catch (\Exception $e) {
                $meta = null;
            }
            $deckChoices[] = ['decklist' => $d, 'meta' => $meta, 'hero_meta' => $heroMeta];
        }

        // load available static campaigns for selection
        $campaignRepo = $this->getDoctrine()->getRepository('AppBundle:Campaign');
        $availableCampaigns = $campaignRepo->findAll();

        // load existing campaignlist rows for this team so editor can show them
        $campaignListRepo = $this->getDoctrine()->getRepository('AppBundle:CampaignList');
        $teamCampaignLists = $campaignListRepo->findBy(['team' => $team]);

    return $this->render('AppBundle:Team:team.form.html.twig', array(
        'form' => $form->createView(),
        'team' => $team,
        'deck_choices' => $deckChoices,
        'available_campaigns' => $availableCampaigns,
        'team_campaignlists' => $teamCampaignLists
    ));
    }

    public function addCampaignAction(Request $request, $team_id)
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $em = $this->getDoctrine()->getManager();
        $team = $em->getRepository('AppBundle:Team')->find($team_id);
        if (!$team) {
            throw $this->createNotFoundException('Team not found');
        }
        // CSRF protection
        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('add_campaign', $token)) {
            // log details to help debugging (do not expose token to users)
            try {
                $sess = $request->getSession();
                $sid = $sess ? $sess->getId() : 'no-session';
                $this->get('logger')->warning('CSRF invalid on addCampaign', ['token' => $token, 'session' => $sid, 'team_id' => $team_id, 'ip' => $request->getClientIp()]);
            } catch (\Exception $e) {
                // ignore logging errors
            }
            $this->addFlash('error', 'CSRF token invalid. Please try again.');
            return $this->redirect($this->generateUrl('team_view', ['team_id' => $team->getId(), 'slug' => $team->getSlug()]));
        }

        $campaignId = $request->request->get('campaign_id');
        $campaign = $em->getRepository('AppBundle:Campaign')->find($campaignId);
        if (!$campaign) {
            $this->addFlash('error', 'Campaign not found');
            return $this->redirect($this->generateUrl('team_view', ['team_id' => $team->getId(), 'slug' => $team->getSlug()]));
        }

        // create CampaignList linking this team to the campaign, initialize empty values
        $cl = new \AppBundle\Entity\CampaignList();
        $cl->setCampaign($campaign);
        $cl->setTeam($team);

        // initialize notes and counters from Campaign definitions
        $rawNotesDef = null;
        if (method_exists($campaign, 'getCampaignNotes') && $campaign->getCampaignNotes()) {
            $rawNotesDef = json_decode($campaign->getCampaignNotes(), true);
        } elseif ($campaign->getScenarioNotes()) {
            $rawNotesDef = json_decode($campaign->getScenarioNotes(), true);
        }
        $rawCountersDef = null;
        if (method_exists($campaign, 'getCampaignCounters') && $campaign->getCampaignCounters()) {
            $rawCountersDef = json_decode($campaign->getCampaignCounters(), true);
        } elseif ($campaign->getScenarioCounters()) {
            $rawCountersDef = json_decode($campaign->getScenarioCounters(), true);
        }

        // if campaign-level lists are provided (numeric arrays of keys), initialize campaign-level storage
        $initCampaignNotes = [];
        $initCampaignCounters = [];
        $initScenarioNotes = [];
        $initScenarioCounters = [];

        if (is_array($rawNotesDef)) {
            if (array_values($rawNotesDef) === $rawNotesDef && count($rawNotesDef) && is_string($rawNotesDef[0])) {
                foreach ($rawNotesDef as $k) { $initCampaignNotes[$k] = ''; }
            } else {
                foreach ($rawNotesDef as $scode => $fields) {
                    $initScenarioNotes[$scode] = [];
                    foreach ($fields as $fname) { $initScenarioNotes[$scode][$fname] = ''; }
                }
            }
        }

        if (is_array($rawCountersDef)) {
            if (array_values($rawCountersDef) === $rawCountersDef && count($rawCountersDef) && is_string($rawCountersDef[0])) {
                foreach ($rawCountersDef as $k) { $initCampaignCounters[$k] = 0; }
            } else {
                foreach ($rawCountersDef as $scode => $fields) {
                    $initScenarioCounters[$scode] = [];
                    foreach ($fields as $cname) { $initScenarioCounters[$scode][$cname] = 0; }
                }
            }
        }

        // initialize player names (4 slots)
        $initPlayers = ['', '', '', ''];

        $cl->setPlayerNames(json_encode($initPlayers));
        $cl->setSelectedScenario(0);

        // store into CampaignList: prefer campaign-level fields, fallback to scenario-shaped storage for legacy
        if (!empty($initCampaignNotes)) {
            $cl->setCampaignNotes(json_encode($initCampaignNotes));
        } else {
            $cl->setCampaignNotes(json_encode($initScenarioNotes));
        }

        if (!empty($initCampaignCounters)) {
            $cl->setCampaignCounters(json_encode($initCampaignCounters));
        } else {
            $cl->setCampaignCounters(json_encode($initScenarioCounters));
        }

        $em->persist($cl);
        $em->flush();

        $this->addFlash('success', 'Campaign added to team.');
        return $this->redirect($this->generateUrl('team_view', ['team_id' => $team->getId(), 'slug' => $team->getSlug()]) . '?active_campaignlist=' . $cl->getId());
    }

    public function editCampaignListAction(Request $request, $campaignlist_id)
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $em = $this->getDoctrine()->getManager();
        $cl = $em->getRepository('AppBundle:CampaignList')->find($campaignlist_id);
        if (!$cl) {
            throw $this->createNotFoundException('CampaignList not found');
        }

        if ($request->isMethod('POST')) {
            // CSRF protection: token id includes campaignlist id
            $token = $request->request->get('_token');
            if (!$this->isCsrfTokenValid('edit_campaign_' . $campaignlist_id, $token)) {
                try {
                    $sess = $request->getSession();
                    $sid = $sess ? $sess->getId() : 'no-session';
                    $this->get('logger')->warning('CSRF invalid on editCampaignList', ['token' => $token, 'session' => $sid, 'campaignlist_id' => $campaignlist_id, 'ip' => $request->getClientIp()]);
                } catch (\Exception $e) {
                    // ignore
                }
                $this->addFlash('error', 'CSRF token invalid. Please try again.');
                $team = $cl->getTeam();
                $teamId = $team ? $team->getId() : null;
                if ($teamId) return $this->redirect($this->generateUrl('team_edit', ['team_id' => $teamId]));
                return $this->redirect($this->generateUrl('team_index'));
            }
            // Save posted values: prefer campaign-level keys, fall back to scenario-level for compatibility
            $notes = $request->request->get('campaign_notes');
            if ($notes === null) { $notes = $request->request->get('scenario_notes'); }
            $counters = $request->request->get('campaign_counters');
            if ($counters === null) { $counters = $request->request->get('scenario_counters'); }
            $team_hps = $request->request->get('team_hps');
            $campaign_cards = $request->request->get('campaign_cards');
            $player_names = $request->request->get('player_names');

            if ($notes !== null) {
                // if campaign-level (flat map), store as associative map; if array inputs from form, json_encode
                $cl->setCampaignNotes(is_string($notes) ? $notes : json_encode($notes));
            }
            if ($counters !== null) {
                $cl->setCampaignCounters(is_string($counters) ? $counters : json_encode($counters));
            }
            if ($team_hps !== null) {
                $cl->setTeamHps(is_string($team_hps) ? $team_hps : json_encode($team_hps));
            }
            if ($campaign_cards !== null) {
                $cl->setCampaignCards(is_string($campaign_cards) ? $campaign_cards : json_encode($campaign_cards));
            }
            if ($player_names !== null) {
                $cl->setPlayerNames(is_string($player_names) ? $player_names : json_encode($player_names));
            }
            $selected_scenario = $request->request->get('selected_scenario');
            if ($selected_scenario !== null) {
                // store as integer
                $cl->setSelectedScenario(intval($selected_scenario));
            }

            $em->persist($cl);
            $em->flush();

            $this->addFlash('success', 'Campaign values saved.');
            // redirect back to team view when possible, otherwise fall back to team_edit
            $team = $cl->getTeam();
            if ($team) {
                try {
                    $slug = $team->getSlug();
                } catch (\Exception $e) {
                    $slug = null;
                }
                if ($slug) {
                    return $this->redirect($this->generateUrl('team_view', ['team_id' => $team->getId(), 'slug' => $slug]) . '?active_campaignlist=' . $cl->getId());
                }
                return $this->redirect($this->generateUrl('team_edit', ['team_id' => $team->getId()]));
            }
            return $this->redirect($this->generateUrl('team_index'));
        }

        // prepare decoded campaign definitions for the template
        $camp = $cl->getCampaign();
        // support campaign-level and scenario-level shapes
        $rawCampNotes = null;
        if (method_exists($camp, 'getCampaignNotes') && $camp->getCampaignNotes()) {
            $rawCampNotes = json_decode($camp->getCampaignNotes(), true);
        } elseif ($camp->getScenarioNotes()) {
            $rawCampNotes = json_decode($camp->getScenarioNotes(), true);
        }
        $rawCampCounters = null;
        if (method_exists($camp, 'getCampaignCounters') && $camp->getCampaignCounters()) {
            $rawCampCounters = json_decode($camp->getCampaignCounters(), true);
        } elseif ($camp->getScenarioCounters()) {
            $rawCampCounters = json_decode($camp->getScenarioCounters(), true);
        }

        $campCampaignNotes = [];
        $campScenarioNotes = [];
        if (is_array($rawCampNotes)) {
            if (array_values($rawCampNotes) === $rawCampNotes && count($rawCampNotes) && is_string(reset($rawCampNotes))) {
                $campCampaignNotes = $rawCampNotes;
            } else {
                $campScenarioNotes = $rawCampNotes;
            }
        }

        $campCampaignCounters = [];
        $campScenarioCounters = [];
        if (is_array($rawCampCounters)) {
            if (array_values($rawCampCounters) === $rawCampCounters && count($rawCampCounters) && is_string(reset($rawCampCounters))) {
                $campCampaignCounters = $rawCampCounters;
            } else {
                $campScenarioCounters = $rawCampCounters;
            }
        }

        $campDefs = [
            'campaign_notes' => $campCampaignNotes,
            'campaign_counters' => $campCampaignCounters,
            'scenario_notes' => $campScenarioNotes,
            'scenario_counters' => $campScenarioCounters,
            'team_hps' => (method_exists($camp, 'getTeamHps') && $camp->getTeamHps()) ? json_decode($camp->getTeamHps(), true) : null,
        ];

        // prepare decoded campaignlist values for the template so Twig doesn't need json_decode
        // decode cl values supporting campaign-level and scenario-level shapes
        $rawClNotes = $cl->getCampaignNotes() ? json_decode($cl->getCampaignNotes(), true) : null;
        $rawClCounters = $cl->getCampaignCounters() ? json_decode($cl->getCampaignCounters(), true) : null;

        $clCampaignNotes = [];
        $clScenarioNotes = [];
        if (is_array($rawClNotes)) {
            if (array_values($rawClNotes) === $rawClNotes && count($rawClNotes) && is_string(reset($rawClNotes))) {
                foreach ($rawClNotes as $k) { $clCampaignNotes[$k] = ''; }
            } else {
                $clScenarioNotes = $rawClNotes;
            }
        }

        $clCampaignCounters = [];
        $clScenarioCounters = [];
        if (is_array($rawClCounters)) {
            if (array_values($rawClCounters) === $rawClCounters && count($rawClCounters) && is_string(reset($rawClCounters))) {
                foreach ($rawClCounters as $k) { $clCampaignCounters[$k] = 0; }
            } else {
                $clScenarioCounters = $rawClCounters;
            }
        }

        $clValues = [
            'campaign_notes' => $clCampaignNotes,
            'campaign_counters' => $clCampaignCounters,
            'scenario_notes' => $clScenarioNotes,
            'scenario_counters' => $clScenarioCounters,
            'team_hps' => $cl->getTeamHps() ? json_decode($cl->getTeamHps(), true) : null,
            'campaign_cards' => $cl->getCampaignCards() ? json_decode($cl->getCampaignCards(), true) : null,
            'player_names' => ($cl->getPlayerNames() ? json_decode($cl->getPlayerNames(), true) : []),
            'selected_scenario' => ($cl->getSelectedScenario() !== null ? intval($cl->getSelectedScenario()) : 0),
        ];

        // render edit form
        return $this->render('AppBundle:Team:campaign.form.html.twig', ['campaignlist' => $cl, 'campaign_defs' => $campDefs, 'campaignlist_values' => $clValues]);
    }

    public function scenarioStatsAction(Request $request, $campaignlist_id, $scenario_code)
    {
        $em = $this->getDoctrine()->getManager();
        $cl = $em->getRepository('AppBundle:CampaignList')->find($campaignlist_id);
        if (!$cl) {
            throw $this->createNotFoundException('CampaignList not found');
        }
        $camp = $cl->getCampaign();
        if (!$camp) {
            throw $this->createNotFoundException('Campaign definition not found');
        }

        // decode campaign modulars mapping (normalized by importer)
        $rawModulars = $camp->getModulars() ? json_decode($camp->getModulars(), true) : [];
        $scenarioModulars = [];
        if (is_array($rawModulars) && isset($rawModulars[$scenario_code])) {
            $scenarioModulars = $rawModulars[$scenario_code];
        }

        // optional: allow selecting a single modular via ?modular_code=... (shows all by default)
        $requested_modular = $request->query->get('modular_code');
        if ($requested_modular) {
            $filtered = [];
            foreach ($scenarioModulars as $m) {
                $mcode = is_array($m) ? ($m['code'] ?? null) : (is_string($m) ? $m : null);
                if ($mcode === $requested_modular) {
                    $filtered[] = $m;
                }
            }
            if (count($filtered)) {
                $scenarioModulars = $filtered;
            }
        }

        // build filtered modular Cardset entities from modular codes
        $cardsetRepo = $em->getRepository('AppBundle:Cardset');
        $filtered_modular_sets = [];
        foreach ($scenarioModulars as $m) {
            $mcode = is_array($m) ? ($m['code'] ?? null) : (is_string($m) ? $m : null);
            if ($mcode) {
                $set = $cardsetRepo->findOneBy(['code' => $mcode]);
                if ($set) $filtered_modular_sets[] = $set;
            }
        }

        // determine villain set: prefer query param, else try scenario code as set code, else use first villain set
        $villain_code = $request->query->get('villain_set');
        $filtered_villain_sets = [];
        if ($villain_code) {
            $vset = $cardsetRepo->findOneBy(['code' => $villain_code]);
            if ($vset) $filtered_villain_sets[] = $vset;
        } else {
            // try to find by scenario_code
            $vset = $cardsetRepo->findOneBy(['code' => $scenario_code]);
            if ($vset) {
                $filtered_villain_sets[] = $vset;
            } else {
                // fallback: all villain sets filtered by type
                $filtered_villain_sets = $this->getDoctrine()->getRepository('AppBundle:Cardset')->findBy(['cardset_type' => $em->getRepository('AppBundle:CardsetType')->findOneBy(['code' => 'villain'])]);
                if (!$filtered_villain_sets) $filtered_villain_sets = [];
            }
        }

        // load cards
        $cards = $this->getDoctrine()->getRepository('AppBundle:Card')->findAll();

        // reuse SearchController logic locally: closures to compute per-set cards and stats
        $that = $this;
        $getCardQuantity = function($card) {
            return method_exists($card, 'getQuantity') && $card->getQuantity() !== null ? $card->getQuantity() : 1;
        };
        $getCardType = function($card) { return $card->getType() ? $card->getType()->getName() : ''; };

        static $availability = [];
        $cardsBySet = function($sets, $cards, $excludeTypes = []) use ($getCardType, $getCardQuantity, $that, &$availability) {
            $excludeTypes = array_merge($excludeTypes, ['ally', 'support', 'upgrade', 'event']);
            $result = [];
            foreach ($sets as $set) {
                $set_code = $set->getCode();
                $result[$set_code] = [];
                foreach ($cards as $card) {
                    if ($card->getCardset() && $card->getCardset()->getCode() === $set_code) {
                        $type_name = strtolower($getCardType($card));
                        if (in_array($type_name, $excludeTypes)) continue;
                        $cardinfo = $that->get('cards_data')->getCardInfo($card, false, true);
                        $pack = $card->getPack();
                        if ($pack) {
                            $pack_code = $pack->getCode();
                            if (!isset($availability[$pack_code])) {
                                $availability[$pack_code] = false;
                                if ($pack->getDateRelease() && $pack->getDateRelease() <= new \DateTime()) {
                                    $availability[$pack_code] = true;
                                }
                            }
                            $cardinfo['available'] = $availability[$pack_code];
                        }
                        $result[$set_code][] = $cardinfo;
                    }
                }
            }
            return $result;
        };

        $setStats = function($cardsBySet) {
            $statsBySet = [];
            foreach ($cardsBySet as $setCode => $setCards) {
                $differentCards = count($setCards);
                $totalCards = 0;
                $totalBoost = 0;
                $totalBoostStar = 0;
                foreach ($setCards as $card) {
                    $quantity = $card['quantity'];
                    $totalCards += $quantity;
                    $totalBoost += ($card['boost'] ?: 0) * $quantity;
                    if (isset($card['boost_star']) && $card['boost_star']) $totalBoostStar += $quantity;
                }
                $averageBoost = $totalCards > 0 ? number_format($totalBoost / $totalCards, 2, '.', '') : '0.00';
                $statsBySet[$setCode] = [
                    'differentCards' => $differentCards,
                    'totalCards' => $totalCards,
                    'totalBoost' => $totalBoost,
                    'totalBoostStar' => $totalBoostStar,
                    'averageBoost' => $averageBoost,
                ];
            }
            return $statsBySet;
        };

        $traitsBySet = function($sets, $cards) {
            $result = [];
            foreach ($sets as $set) {
                $set_code = $set->getCode();
                $traits = [];
                foreach ($cards as $card) {
                    if ($card->getCardset() && $card->getCardset()->getCode() === $set_code) {
                        $card_traits = $card->getTraits();
                        if (is_string($card_traits) && trim($card_traits) !== '') {
                            // split on period+space or period at end, or comma/semicolon — avoid splitting internal dots in acronyms like S.H.I.E.L.D.
                            $matches = preg_split('/(?:\.\s+|\.(?:$)|[;,])/', $card_traits);
                            foreach ($matches as $trait) {
                                $trait = trim($trait);
                                if ($trait === "A.I.M.") $trait = "AIM";
                                if ($trait === "S.H.I.E.L.D.") $trait = "SHIELD";
                                if ($trait === "S.W.O.R.D.") $trait = "SWORD";
                                if ($trait !== '') $traits[$trait] = true;
                            }
                        }
                    }
                }
                ksort($traits);
                $result[$set_code] = array_keys($traits);
            }
            return $result;
        };

        $type_label = [
            'Minion' => 'minion',
            'Treachery' => 'treachery',
            'Attachment' => 'attachment',
            'Environment' => 'environment',
            'Side Scheme' => 'side scheme',
            'Main Scheme' => 'main scheme',
            'Ally' => 'ally',
            'Upgrade' => 'upgrade',
            'Support' => 'support',
            'Event' => 'event'
        ];

        $setTypeCounts = function($sets, $cards_by_set, $type_label) {
            $counts = [];
            foreach ($sets as $set) {
                $set_code = $set->getCode();
                $counts[$set_code] = [];
                foreach ($type_label as $type => $label) {
                    $count = 0;
                    foreach ($cards_by_set[$set_code] as $card) {
                        if (isset($card['type_name']) && $card['type_name'] === $type) {
                            $qty = isset($card['quantity']) && $card['quantity'] !== null ? $card['quantity'] : 1;
                            $count += $qty;
                        }
                    }
                    $counts[$set_code][$type] = $count;
                }
            }
            return $counts;
        };

        $setBoostCounts = function($sets, $cards_by_set) {
            $counts = [];
            foreach ($sets as $set) {
                $set_code = $set->getCode();
                $counts[$set_code] = ['0' => 0, '1' => 0, '2' => 0, '3+' => 0];
                foreach ($cards_by_set[$set_code] as $card) {
                    $boost = isset($card['boost']) ? intval($card['boost']) : 0;
                    $qty = isset($card['quantity']) ? $card['quantity'] : 1;
                    if ($boost === 0) $counts[$set_code]['0'] += $qty;
                    elseif ($boost === 1) $counts[$set_code]['1'] += $qty;
                    elseif ($boost === 2) $counts[$set_code]['2'] += $qty;
                    else $counts[$set_code]['3+'] += $qty;
                }
            }
            return $counts;
        };

        // compute
        $modular_cards_by_set = $cardsBySet($filtered_modular_sets, $cards);
        $modular_stats_by_set = $setStats($modular_cards_by_set);
        $modular_traits_by_set = $traitsBySet($filtered_modular_sets, $cards);
        $modular_set_type_counts = $setTypeCounts($filtered_modular_sets, $modular_cards_by_set, $type_label);
        $modular_set_boost_counts = $setBoostCounts($filtered_modular_sets, $modular_cards_by_set);

        $villain_cards_by_set = $cardsBySet($filtered_villain_sets, $cards, ['villain', 'main scheme']);
        $villain_stats_by_set = $setStats($villain_cards_by_set);
        $villain_traits_by_set = $traitsBySet($filtered_villain_sets, $cards);
        $villain_set_type_counts = $setTypeCounts($filtered_villain_sets, $villain_cards_by_set, $type_label);
        $villain_set_boost_counts = $setBoostCounts($filtered_villain_sets, $villain_cards_by_set);

        // combine
        $selected_villain_code = $filtered_villain_sets ? $filtered_villain_sets[0]->getCode() : null;
        // sum modulars + villain
        $sum_differentCards = 0; $sum_totalCards = 0; $sum_totalBoost = 0; $sum_totalBoostStar = 0;
        foreach ($modular_stats_by_set as $s) {
            $sum_differentCards += $s['differentCards'];
            $sum_totalCards += $s['totalCards'];
            $sum_totalBoost += $s['totalBoost'];
            $sum_totalBoostStar += $s['totalBoostStar'];
        }
        if ($selected_villain_code && isset($villain_stats_by_set[$selected_villain_code])) {
            $v = $villain_stats_by_set[$selected_villain_code];
            $sum_differentCards += $v['differentCards'];
            $sum_totalCards += $v['totalCards'];
            $sum_totalBoost += $v['totalBoost'];
            $sum_totalBoostStar += $v['totalBoostStar'];
        }
        $sum_averageBoost = $sum_totalCards > 0 ? number_format($sum_totalBoost / $sum_totalCards, 2, '.', '') : '0.00';

        $combined_stats = [
            'differentCards' => $sum_differentCards,
            'totalCards' => $sum_totalCards,
            'totalBoost' => $sum_totalBoost,
            'totalBoostStar' => $sum_totalBoostStar,
            'averageBoost' => $sum_averageBoost
        ];

        $combined_type_counts = [];
        foreach ($modular_set_type_counts as $mc) {
            foreach ($mc as $type => $cnt) { $combined_type_counts[$type] = ($combined_type_counts[$type] ?? 0) + $cnt; }
        }
        if ($selected_villain_code && isset($villain_set_type_counts[$selected_villain_code])) {
            foreach ($villain_set_type_counts[$selected_villain_code] as $type => $cnt) { $combined_type_counts[$type] = ($combined_type_counts[$type] ?? 0) + $cnt; }
        }

        $combined_boost_counts = ['0'=>0,'1'=>0,'2'=>0,'3+'=>0];
        foreach ($modular_set_boost_counts as $mb) { foreach ($mb as $k=>$v) { $combined_boost_counts[$k] = ($combined_boost_counts[$k] ?? 0) + $v; } }
        if ($selected_villain_code && isset($villain_set_boost_counts[$selected_villain_code])) { foreach ($villain_set_boost_counts[$selected_villain_code] as $k=>$v) { $combined_boost_counts[$k] = ($combined_boost_counts[$k] ?? 0) + $v; } }

        $combined_traits = [];
        foreach ($modular_traits_by_set as $tset) { $combined_traits = array_merge($combined_traits, $tset); }
        if ($selected_villain_code && isset($villain_traits_by_set[$selected_villain_code])) $combined_traits = array_merge($combined_traits, $villain_traits_by_set[$selected_villain_code]);
        // deduplicate traits
        $combined_traits = array_values(array_unique($combined_traits));

        $modular_name = implode(' + ', array_map(function($s){ return $s->getName(); }, $filtered_modular_sets));
        $villain_name = $selected_villain_code ? ($filtered_villain_sets[0]->getName() ?? '') : '';
        $combined_set_name = trim($modular_name . ' + ' . $villain_name, ' +');

        return $this->render('AppBundle:Search:story.html.twig', [
            'pagetitle' => 'Scenario stats',
            'cards' => $cards,
            'type_label' => $type_label,
            'filtered_modular_sets' => $filtered_modular_sets,
            'modular_set_type_counts' => $modular_set_type_counts,
            'modular_set_boost_counts' => $modular_set_boost_counts,
            'modular_traits_by_set' => $modular_traits_by_set,
            'modular_cards_by_set' => $modular_cards_by_set,
            'modular_set_stats' => $modular_stats_by_set,
            'filtered_villain_sets' => $filtered_villain_sets,
            'villain_set_type_counts' => $villain_set_type_counts,
            'villain_set_boost_counts' => $villain_set_boost_counts,
            'villain_cards_by_set' => $villain_cards_by_set,
            'villain_set_stats' => $villain_stats_by_set,
            'villain_traits_by_set' => $villain_traits_by_set,
            'combined_stats' => $combined_stats,
            'combined_type_counts' => $combined_type_counts,
            'combined_boost_counts' => $combined_boost_counts,
            'combined_traits' => $combined_traits,
            'combined_set_name' => $combined_set_name,
            'selected_modular_code' => $filtered_modular_sets ? $filtered_modular_sets[0]->getCode() : null,
            // provide initial slots to the stories template so it can default correctly
            'slots' => ($filtered_modular_sets ? count($filtered_modular_sets) : 1),
            'selected_villain_code' => $selected_villain_code,
        ]);
    }

    public function deleteAction(Request $request)
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $teamId = $request->request->get('team_id');
        $em = $this->getDoctrine()->getManager();
        $team = $em->getRepository('AppBundle:Team')->find($teamId);
        if (!$team) {
            throw $this->createNotFoundException('Team not found');
        }
        if (!$this->isGranted('DELETE', $team)) {
            throw $this->createAccessDeniedException();
        }

        $em->remove($team);
        $em->flush();

        return $this->redirect($this->generateUrl('team_index'));
    }

    /**
     * Check whether a deck is part of a team owned by the current user.
     * Returns JSON: { found: true/false, team_id: <id> }
     */
    public function checkDeckAction(Request $request, $deck_id)
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $user = $this->getUser();
        $em = $this->getDoctrine()->getManager();

        $qb = $em->createQueryBuilder();
        $qb->select('t')
            ->from('AppBundle:Team', 't')
            ->join('t.decks', 'd')
            ->where('d.id = :deck_id')
            ->andWhere('t.owner = :owner')
            ->setParameter('deck_id', $deck_id)
            ->setParameter('owner', $user);

        $team = $qb->getQuery()->setMaxResults(1)->getOneOrNullResult();

        if ($team) {
            return new JsonResponse(['found' => true, 'team_id' => $team->getId()]);
        }
        return new JsonResponse(['found' => false]);
    }
}
