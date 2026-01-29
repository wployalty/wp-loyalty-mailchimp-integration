import React from 'react';
import Icon from "./Icon";

const EarnPointCard = ({earn_point}) => {

    return <div key={earn_point.id}

                className={`flex cursor-pointer w-full shadow-card_1 rounded-xl
                                         items-center justify-between bg-white gap-x-2 lg:gap-x-3 px-2 xl:px-3 py-2 xl:py-3 `}>
        <div className={`flex items-center justify-start w-full lg:gap-x-4 gap-x-3`}>
            <Icon icon={`${earn_point.icon}`} fontSize={`text-3xl lg:text-4xl`}/>
            <div className={`flex flex-col gap-y-1 w-full `}>
                <p className={'text-xs lg:text-sm font-bold text-dark'}>
                    {earn_point.title}
                </p>
                <p className={`text-xs lg:text-sm font-normal text-light`}>
                    {earn_point.description}
                </p>
            </div>
            <div>
                <Icon icon={"arrow_right"}/>
            </div>
        </div>
    </div>

};

export default EarnPointCard;