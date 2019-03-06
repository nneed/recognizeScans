#!/usr/bin/env python

import sys
import numpy as np
import cv2
import math
import os.path
import sys

#print(sys.argv[1]) # prints python_script.py

file_path = sys.argv[1] 
file_name = sys.argv[2] 
file_path_result = '/var/www/html/queue/runtime/scans/result'
if not os.path.exists(file_path_result):
  os.makedirs(file_path_result)

if os.path.exists(file_path):
    image = cv2.imread(file_path)
    height, width = image.shape[:2]

    # cv2.resize(image,(800, int(height*800/width)), interpolation = cv2.INTER_CUBIC)
    image = cv2.resize(image,(1000, int(height*1000/width)), interpolation = cv2.INTER_CUBIC)

    gray = cv2.cvtColor(image, cv2.COLOR_BGR2GRAY)
    gray = cv2.GaussianBlur(gray, (7, 3), 0)
    #cv2.imwrite("gray/{}.jpg".format(x), gray)

    # распознавание контуров
    edged = cv2.Canny(gray, 10, 250)
   # cv2.imwrite("edged/{}.jpg".format(x), edged) 


    # создайте и примените закрытие
    kernel = cv2.getStructuringElement(cv2.MORPH_RECT, (3, 3))
    closed = cv2.morphologyEx(edged, cv2.MORPH_CLOSE, kernel)
   # cv2.imwrite("closed/{}.jpg".format(x), closed)

    # найдите контуры в изображении и подсчитайте количество книг
    cnts = cv2.findContours(closed.copy(), cv2.RETR_EXTERNAL, cv2.CHAIN_APPROX_SIMPLE)[1]
    total = 0

    # цикл по контурам
    i = 0
    sq = 0
    crop = []
    areaCrop = []
    ratioCrop = []

    for c in cnts:
        # аппроксимируем (сглаживаем) контур
        peri = cv2.arcLength(c, True)
       # approx = cv2.approxPolyDP(c, 0.07 * peri, True)
        approx = cv2.approxPolyDP(c, 0.02 * peri, True)

        # если у контура 4 вершины, предполагаем, что это книга
        if len(approx) == 4:
            rect = cv2.minAreaRect(c) # пытаемся вписать прямоугольник
            box = cv2.boxPoints(rect) # поиск четырех вершин прямоугольника
            box = np.int0(box) # округление координат
            center = (int(rect[0][0]),int(rect[0][1]))
            area = int(rect[1][0]*rect[1][1]) # вычисление площади

            arrX = [ box[0][0],box[1][0],box[2][0],box[3][0] ]
            arrY = [ box[0][1],box[1][1],box[2][1],box[3][1] ]

            minX = min(arrX)
            maxX = max(arrX)

            minY = min(arrY)
            maxY = max(arrY)

            widthApp = maxX - minX
            heightApp = maxY - minY

            ratio = float(widthApp/heightApp)

          # if area>48000:
            if ratio > 3.0 and widthApp/heightApp < 4.7 and area>45000:
                crop = closed[minY:maxY,minX:maxX]
            else:
                if area>10000:
                    areaCrop.append(area) 
                ratioCrop.append(ratio) 

            if (widthApp > 15 and widthApp < 45) and (heightApp > 15 and heightApp < 45) :
                # cropSQ = closed[minY:maxY,minX:maxX]
                # cv2.imwrite("signs/signSQ{}{}.jpg".format(x,sq), cropSQ)
                sq = sq+1
            # else:
            #     continue

           # print(x,'--', i)                
               #crop = image[approx[0][0][1]:approx[2][0][1],approx[0][0][0]:approx[2][0][0]]


            

            cv2.drawContours(image, [approx], -1, (0, 255, 0), 4)

        #    crop = image[1272:1299, 114:141]
           # print(crop)
            #print(approx)
            #print(box)
        #   print(crop)
           # break

            i += 1
            #break
    # print(len(crop))
    # print(sq)
    if len(crop) > 0 and (sq == 3 or sq == 2) :
       # print('crop')

       heightCrop, widthCrop = crop.shape[:2]
       white = 0
       allArea = heightCrop * widthCrop 
       for x in range(0,widthCrop-1):
            for y in range(0,heightCrop-1):
                pix = crop[y,x]
                white = white+1 if pix == 255 else white
       res = (white * 100 / allArea) > 4
       signs = file_path_result+"/signs"
       if not os.path.exists(signs):
         os.makedirs(signs)
       cv2.imwrite(signs + "/"+file_name+".jpg", crop)
       #cv2.imwrite(signs+"/1.jpg", crop)       
    else:
   #    print(x,'cntapprox:',len(approx),'sq:', sq,'areaCrop:',areaCrop,'ratioCrop:',ratioCrop)
    #   print('')
      notfound = file_path_result+"/notfound"
      if not os.path.exists(notfound):
        os.makedirs(notfound)
      cv2.imwrite(notfound + "/"+file_name+".jpg", image)
    # # print(area);
      res = False
    # # показываем результирующее изображение

    output = file_path_result+"/output"
    if not os.path.exists(output):
      os.makedirs(output)
    cv2.imwrite(output + "/"+file_name+".jpg", image)
    print(res)




# input("\n\nНажмите Enter чтобы выйти .")

# // удаляем окно


# hsv_min = np.array((0, 54, 5), np.uint8)
# hsv_max = np.array((187, 255, 253), np.uint8)

# color_blue = (255,0,0)
# color_yellow = (0,255,255)
 
# if __name__ == '__main__':
#     fn = 'image2.jpg' # имя файла, который будем анализировать
#     img = cv.imread(fn)

#     hsv = cv.cvtColor( img, cv.COLOR_BGR2HSV ) # меняем цветовую модель с BGR на HSV
#     thresh = cv.inRange( hsv, hsv_min, hsv_max ) # применяем цветовой фильтр
#     _, contours0, hierarchy = cv.findContours( thresh.copy(), cv.RETR_TREE, cv.CHAIN_APPROX_SIMPLE)

#     # перебираем все найденные контуры в цикле
#     for cnt in contours0:
#         rect = cv.minAreaRect(cnt) # пытаемся вписать прямоугольник
#         box = cv.boxPoints(rect) # поиск четырех вершин прямоугольника
#         box = np.int0(box) # округление координат
#         center = (int(rect[0][0]),int(rect[0][1]))
#         area = int(rect[1][0]*rect[1][1]) # вычисление площади

#         # вычисление координат двух векторов, являющихся сторонам прямоугольника
#         edge1 = np.int0((box[1][0] - box[0][0],box[1][1] - box[0][1]))
#         edge2 = np.int0((box[2][0] - box[1][0], box[2][1] - box[1][1]))

#         # выясняем какой вектор больше
#         usedEdge = edge1
#         if cv.norm(edge2) > cv.norm(edge1):
#             usedEdge = edge2
#         reference = (1,0) # горизонтальный вектор, задающий горизонт

#         # вычисляем угол между самой длинной стороной прямоугольника и горизонтом
#         angle = 180.0/math.pi * math.acos((reference[0]*usedEdge[0] + reference[1]*usedEdge[1]) / (cv.norm(reference) *cv.norm(usedEdge)))
     
#         if area > 500:
#             cv.drawContours(img,[box],0,(255,0,0),2) # рисуем прямоугольник
#             cv.circle(img, center, 5, color_yellow, 2) # рисуем маленький кружок в центре прямоугольника
#             # выводим в кадр величину угла наклона
#             cv.putText(img, "%d" % int(angle), (center[0]+20, center[1]-20), 
#                        cv.FONT_HERSHEY_SIMPLEX, 1, color_yellow, 2)

#     cv.imshow('contours', img)

#     cv.waitKey()
#     cv.destroyAllWindows()